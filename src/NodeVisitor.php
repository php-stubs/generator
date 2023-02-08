<?php
namespace StubsGenerator;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Const_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * On node traversal, this visitor converts any AST to one just containing stub
 * definitions, removing anything uninteresting.
 *
 * @internal
 */
class NodeVisitor extends NodeVisitorAbstract
{
    /** @var bool */
    private $needsFunctions;
    /** @var bool */
    private $needsClasses;
    /** @var bool */
    private $needsTraits;
    /** @var bool */
    private $needsInterfaces;
    /** @var bool */
    private $needsDocumentedGlobals;
    /** @var bool */
    private $needsUndocumentedGlobals;
    /** @var bool */
    private $needsConstants;
    /** @var bool */
    private $nullifyGlobals;
    /** @var bool */
    private $includeInaccessibleClassNodes;

    /**
     * @psalm-suppress PropertyNotSetInConstructor
     * @var Node[]
     */
    protected $stack;

    /** @var Namespace_[] */
    private $namespaces = [];
    /** @var Namespace_ */
    private $globalNamespace;
    /** @var Node[] */
    private $globalExpressions = [];
    /** @var ClassLikeWithDependencies[] */
    private $classLikes = [];
    /** @var true[] */
    private $resolvedClassLikes = [];
    /** @var Namespace_[] */
    private $classLikeNamespaces = [];
    /** @var Namespace_|null */
    private $currentClassLikeNamespace = null;

    /** @var bool */
    private $isInDeclaration = false;
    /** @var bool */
    private $isInIf = false;

    /**
     * @var int[][]
     * @psalm-var array<string, array<string, int>>
     */
    private $counts = [
        'functions' => [],
        'classes' => [],
        'interfaces' => [],
        'traits' => [],
        'constants' => [],
        'globals' => [],
    ];

    /**
     * @param int $symbols Set of symbol types to include stubs for.
     */
    public function init(int $symbols = StubsGenerator::DEFAULT, array $config = [])
    {
        $this->needsFunctions = ($symbols & StubsGenerator::FUNCTIONS) !== 0;
        $this->needsClasses = ($symbols & StubsGenerator::CLASSES) !== 0;
        $this->needsTraits = ($symbols & StubsGenerator::TRAITS) !== 0;
        $this->needsInterfaces = ($symbols & StubsGenerator::INTERFACES) !== 0;
        $this->needsDocumentedGlobals = ($symbols & StubsGenerator::DOCUMENTED_GLOBALS) !== 0;
        $this->needsUndocumentedGlobals = ($symbols & StubsGenerator::UNDOCUMENTED_GLOBALS) !== 0;
        $this->needsConstants = ($symbols & StubsGenerator::CONSTANTS) !== 0;

        $this->nullifyGlobals = !empty($config['nullify_globals']);
        $this->includeInaccessibleClassNodes = ($config['include_inaccessible_class_nodes'] ?? false) === true;

        $this->globalNamespace = new Namespace_();
    }

    public function beforeTraverse(array $nodes)
    {
        $this->stack = [];
    }

    public function enterNode(Node $node)
    {
        $this->stack[] = $node;

        if ($node instanceof Namespace_) {
            // We always need to parse the children of namespaces.
            return;
        }

        if (($this->needsClasses && $node instanceof Class_)
            || ($this->needsInterfaces && $node instanceof Interface_)
            || ($this->needsTraits && $node instanceof Trait_)
        ) {
            // We'll need to parse all descendents of these nodes (if we plan to
            // include them in the stubs at all) so we get method, property, and
            // constant declarations.
            $this->isInDeclaration = true;
        } elseif ($node instanceof Function_ || $node instanceof ClassMethod) {
            // We can just delete function or method bodies for our stubs.  In
            // the future we may want to parse them for constant definitions or
            // the like.
            if ($node->stmts) {
                $node->stmts = [];
            }
            // We need to parse all the (non-statement) descendents of these
            // nodes so that constant or class references in the function
            // signatures are fully qualified by the `NameResolver` visitor.
            // (This will already be `true` if it's a ClassMethod.)
            $this->isInDeclaration = true;
        } elseif ($node instanceof Expression
            && $node->expr instanceof Assign
        ) {
            // Since we don't parse any the bodies of any statements which can
            // hold variable assignments---other than namespaces---we know these
            // assigns are for globals.  Check if we are assigning to `$GLOBALS`
            // with a simple string that's a valid variable identifier.  If so,
            // convert it to a normal variable assignment.
            if (count($this->stack) === 1
                && $node->expr->var instanceof ArrayDimFetch
                && $node->expr->var->var instanceof Variable
                && $node->expr->var->var->name === 'GLOBALS'
                && $node->expr->var->dim instanceof String_
                && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $node->expr->var->dim->value)
            ) {
                $node->expr->var = new Variable($node->expr->var->dim->value);
            }
            // Ensure that class or constant references are fully qualified.
            $this->isInDeclaration = true;
            if ($this->nullifyGlobals) {
                $node->expr->expr = new ConstFetch(new Name('null'));
            }
        } elseif ($node instanceof Const_) {
            $this->isInDeclaration = true;
        } elseif (
            $node instanceof Expression &&
            $node->expr instanceof FuncCall &&
            $node->expr->name instanceof Name &&
            $node->expr->name->parts[0] === 'define'
        ) {
            $this->isInDeclaration = true;
        } elseif ($node instanceof If_) {
            if (!$this->isInIf) {
                // We'll examine the first level inside of an if statement to
                // look for function/class/etc. declarations.
                $this->isInIf = true;
                return; // Traverse children.
            }
        }

        if (!$this->isInDeclaration) {
            // Don't bother parsing descendents of uninteresting nodes.
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }
    }

    public function leaveNode(Node $node, bool $preserveStack = false)
    {
        if (!$preserveStack) {
            array_pop($this->stack);
        }
        $parent = $this->stack[count($this->stack) - 1] ?? null;

        if (($node instanceof Expression && $node->expr instanceof Assign)
            || $node instanceof Function_
            || $node instanceof Class_
            || $node instanceof Interface_
            || $node instanceof Trait_
            || $node instanceof Const_
            || (
                $node instanceof Expression &&
                $node->expr instanceof FuncCall &&
                $node->expr->name instanceof Name &&
                $node->expr->name->parts[0] === 'define'
            )
        ) {
            // We're leaving one of these.
            $this->isInDeclaration = false;
        }

        if ($node instanceof If_) {
            // Replace the if statement with its set of children, but only those
            // that we want.  Have to manually call leaveNode on each; it won't
            // be called automatically..
            $stmts = [];
            foreach ($node->stmts as $stmt) {
                if ($this->leaveNode($stmt, true) !== NodeTraverser::REMOVE_NODE) {
                    $stmt = $stmt;
                }
            }
            // We're leaving it.
            $this->isInIf = false;
            return $stmts;
        }

        if ($node instanceof Namespace_) {
            $this->namespaces[] = $node;
            return;
        }

        if ($node instanceof Name) {
            // Can't delete namespace names!
            return;
        }

        if ($parent && !($parent instanceof Namespace_)) {
            // Implies `$parent instanceof ClassLike`, which means $node is a
            // either a method, property, or constant, or its part of the
            // declaration itself (e.g., `extends`).

            if (!$this->includeInaccessibleClassNodes && $parent instanceof Class_ && ($node instanceof ClassMethod || $node instanceof ClassConst || $node instanceof Property)) {
                if ($node->isPrivate() || ($parent->isFinal() && $node->isProtected())) {
                    return NodeTraverser::REMOVE_NODE;
                }
            }

            return;
        }

        $namespaceName = ($parent && $parent->name) ? $parent->name->toString() : '';

        if ($this->needsNode($node, $namespaceName)) {
            if ($node instanceof ClassLike) {
                // Ignore anonymous classes.
                if ($node->name) {
                    $clwd = new ClassLikeWithDependencies($node, $namespaceName);
                    $this->classLikes[$clwd->fullyQualifiedName] = $clwd;
                }
            } elseif ($parent) {
                // If we're here, `$parent` is a namespace.  Let's just keep the
                // `$node` around in `$parent->stmts`.
                return; // Don't remove.
            } elseif ($node instanceof Stmt) {
                // Anything other than a namespace which doesn't have a parent
                // node must belong in the global namespace. We can still remove
                // the `$node` from the current list of statements since we're
                // stashing it for later no matter what.
                $this->globalNamespace->stmts[] = $node;
            } else {
                // Technically only statements should be added to the global
                // namespace; that said, these will be bundled in with the
                // global namespace when the code is generated anyway.
                $this->globalExpressions[] = $node;
            }
        }

        // Any other top level junk we are happy to remove.
        return NodeTraverser::REMOVE_NODE;
    }

    public function afterTraverse(array $nodes)
    {
        // Don't keep any empty namespaces.
        $this->namespaces = array_filter($this->namespaces, function (Namespace_ $ns): bool {
            return (bool) $ns->stmts;
        });
    }

    /**
     * Returns the stored set of stub nodes which are built up during traversal.
     *
     * @return Node[]
     */
    public function getStubStmts(): array
    {
        foreach ($this->classLikes as $classLike) {
            $this->resolveClassLike($classLike);
        }

        if ($this->allAreGlobal($this->namespaces) && $this->allAreGlobal($this->classLikeNamespaces)) {
            return array_merge(
                $this->reduceStmts($this->classLikeNamespaces),
                $this->reduceStmts($this->namespaces),
                $this->globalNamespace->stmts,
                $this->globalExpressions
            );
        }

        return array_merge(
            $this->classLikeNamespaces,
            $this->namespaces,
            $this->globalNamespace->stmts ? [$this->globalNamespace] : [],
            $this->globalExpressions ? [new Namespace_(null, $this->globalExpressions)] : []
        );
    }

    /**
     * Returns the counts of all symbols included in the stubs, grouped by type.
     *
     * These counts are built up during traveral.
     *
     * @psalm-return array<string, array<string, int>>
     */
    public function getCounts(): array
    {
        return $this->counts;
    }

    /**
     * Determines if we should keep the given `$node` in `$this->leaveNode()`.
     *
     * @param Node $node
     * @param string $namespace The namespace we're in.
     *
     * @return bool
     */
    private function needsNode(Node $node, string $namespace): bool
    {
        $fullyQualifiedName = ($node instanceof Function_ || $node instanceof ClassLike)
            ? '\\' . ltrim("{$namespace}\\{$node->name}", '\\')
            : '';

        if ($node instanceof Function_) {
            return $this->needsFunctions
                && $this->count('functions', $fullyQualifiedName)
                && !function_exists($fullyQualifiedName);
        }

        if ($node instanceof Class_) {
            return $this->needsClasses
                && $this->count('classes', $fullyQualifiedName)
                && !class_exists($fullyQualifiedName);
        }

        if ($node instanceof Interface_) {
            return $this->needsInterfaces
                && $this->count('interfaces', $fullyQualifiedName)
                && !interface_exists($fullyQualifiedName);
        }

        if ($node instanceof Trait_) {
            return $this->needsTraits
                && $this->count('traits', $fullyQualifiedName)
                && !trait_exists($fullyQualifiedName);
        }

        if ($this->needsConstants) {
            if ($node instanceof Const_) {
                $node->consts = \array_filter(
                    $node->consts,
                    function (\PhpParser\Node\Const_ $const) {
                        $fullyQualifiedName = $const->name->name;
                        return $this->count('constants', $fullyQualifiedName)
                            && !defined($fullyQualifiedName);
                    }
                );

                return count($node->consts) > 0;
            }

            if (
                $node instanceof Expression &&
                $node->expr instanceof FuncCall &&
                $node->expr->name instanceof Name &&
                $node->expr->name->parts[0] === 'define'
            ) {
                $fullyQualifiedName = $node->expr->args[0]->value->value;

                return $this->count('constants', $fullyQualifiedName)
                    && !defined($fullyQualifiedName);
            }
        }

        if (($this->needsDocumentedGlobals || $this->needsUndocumentedGlobals)
            && !$this->isInIf // Don't keep conditionally declared globals.
            && $node instanceof Expression
            && $node->expr instanceof Assign
            && $node->expr->var instanceof Variable
            && is_string($node->expr->var->name)
        ) {
            $this->count('globals', $node->expr->var->name);
            // We'll keep regular global variable declarations, depending on
            // whether or not they are documented.
            if ($node->getDocComment()) {
                return $this->needsDocumentedGlobals;
            } else {
                return $this->needsUndocumentedGlobals;
            }
        }

        return false;
    }

    /**
     * Keeps a count of declarations by type and name of node.
     *
     * @param string $type      One of `array_keys($this->counts)`.
     * @param string|null $name Name of the node.  Theoretically could be null.
     *
     * @return bool If true, this is the first declaration of this type with
     *              this name, so it can be safely included.
     */
    private function count(string $type, string $name = null): bool
    {
        assert(isset($this->counts[$type]), 'Expected valid `$type`');

        if (!$name) {
            return false;
        }

        return ($this->counts[$type][$name] = ($this->counts[$type][$name] ?? 0) + 1) === 1;
    }

    /**
     * Populates the `classLikeNamespaces` property with namespaces with classes
     * declared in a valid order.
     *
     * @param ClassLikeWithDependencies $clwd
     * @return void
     */
    private function resolveClassLike(ClassLikeWithDependencies $clwd): void
    {
        if (isset($this->resolvedClassLikes[$clwd->fullyQualifiedName])) {
            // Already resolved.
            return;
        }
        $this->resolvedClassLikes[$clwd->fullyQualifiedName] = true;

        foreach ($clwd->dependencies as $dependencyName) {
            if (isset($this->classLikes[$dependencyName])) {
                $this->resolveClassLike($this->classLikes[$dependencyName]);
            }
        }

        if (!$this->currentClassLikeNamespace) {
            $namespaceMatches = false;
        } elseif ($this->currentClassLikeNamespace->name) {
            $namespaceMatches = $this->currentClassLikeNamespace->name->toString() === $clwd->namespace;
        } else {
            $namespaceMatches = !$clwd->namespace;
        }

        // Reduntant check to make Psalm happy.
        if ($this->currentClassLikeNamespace && $namespaceMatches) {
            $this->currentClassLikeNamespace->stmts[] = $clwd->node;
        } else {
            $name = $clwd->namespace ? new Name($clwd->namespace) : null;
            $this->currentClassLikeNamespace = new Namespace_($name, [$clwd->node]);
            $this->classLikeNamespaces[] = $this->currentClassLikeNamespace;
        }
    }

    /**
     * Determines if each namespace in the list is a global namespace.
     *
     * @param Namespace_[] $namespaces
     * @return bool
     */
    private function allAreGlobal(array $namespaces): bool
    {
        foreach ($namespaces as $namespace) {
            if ($namespace->name && $namespace->name->toString() !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Merges the statements of each namespace into one array.
     *
     * @param Namespace_[] $namespaces
     * @return Stmt[]
     */
    private function reduceStmts(array $namespaces): array
    {
        $stmts = [];
        foreach ($namespaces as $namespace) {
            foreach ($namespace->stmts as $stmt) {
                $stmts[] = $stmt;
            }
        }
        return $stmts;
    }
}
