<?php
namespace StubsGenerator;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
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

    /**
     * @psalm-suppress PropertyNotSetInConstructor
     * @var Node[]
     */
    private $stack;

    /** @var Namespace_[] */
    private $namespaces = [];
    /** @var Namespace_ */
    private $globalNamespace;
    /** @var Node[] */
    private $globalExpressions = [];

    /** @var bool */
    private $isInDeclaration = false;

    /**
     * @var int[][]
     * @psalm-var array<string, array<string, int>>
     */
    private $counts = [
        'functions' => [],
        'classes' => [],
        'interfaces' => [],
        'traits' => [],
        'globals' => [],
    ];

    /**
     * @param int $symbols Set of symbol types to include stubs for.
     */
    public function __construct(int $symbols = StubsGenerator::DEFAULT)
    {
        $this->needsFunctions = $symbols & StubsGenerator::FUNCTIONS;
        $this->needsClasses = $symbols & StubsGenerator::CLASSES;
        $this->needsTraits = $symbols & StubsGenerator::TRAITS;
        $this->needsInterfaces = $symbols & StubsGenerator::INTERFACES;
        $this->needsDocumentedGlobals = $symbols & StubsGenerator::DOCUMENTED_GLOBALS;
        $this->needsUndocumentedGlobals = $symbols & StubsGenerator::UNDOCUMENTED_GLOBALS;

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
        } elseif ($node instanceof Assign) {
            // Since we don't parse any the bodies of any statements which can
            // hold variable assignments---other than namespaces---we know these
            // assigns are for globals.  Check if we are assigning to `$GLOBALS`
            // with a simple string that's a valid variable identifier.  If so,
            // convert it to a normal variable assignment.
            if (count($this->stack) === 1
                && $node->var instanceof ArrayDimFetch
                && $node->var->var instanceof Variable
                && $node->var->var->name === 'GLOBALS'
                && $node->var->dim instanceof String_
                && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $node->var->dim->value)
            ) {
                $node->var = new Variable($node->var->dim->value);
            }
            // Ensure that class or constant references are fully qualified.
            $this->isInDeclaration = true;
        } elseif ($node instanceof If_) {
            // Unwrap simple conditional declarations, but only if the wrapped
            // declaration won't redefine something we already have in this
            // version of PHP.  That is, ignore old polyfill declarations.
            $first = $node->stmts[0] ?? null;
            if ($first && (
                ($first instanceof Function_ && !function_exists($first->name))
                || ($first instanceof Class_ && !class_exists($first->name))
                || ($first instanceof Interface_ && !interface_exists($first->name))
                || ($first instanceof Trait_ && !trait_exists($first->name))
            )) {
                // Nested class methods traversed, but this won't be.
                if ($first instanceof Function_ && $first->stmts) {
                    $first->stmts = [];
                }

                return $first;
            }
        }

        if (!$this->isInDeclaration) {
            // Don't bother parsing descendents of uninteresting nodes.
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }
    }

    public function leaveNode(Node $node)
    {
        array_pop($this->stack);
        $parent = $this->stack[count($this->stack) - 1] ?? null;

        if ($node instanceof Assign
            || $node instanceof Function_
            || $node instanceof Class_
            || $node instanceof Interface_
            || $node instanceof Trait_
        ) {
            // We're leaving one of these.
            $this->isInDeclaration = false;
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
            return;
        }

        $namespaceName = ($parent && $parent->name) ? $parent->name->toString() : '';

        if ($this->needsNode($node, $namespaceName)) {
            if ($parent) {
                // If we're here, `$parent` is a namespace.  Let's just keep the
                // `$node` around in `$parent->stmts`.
                return;
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

    /**
     * Returns the stored set of stub nodes which are built up during traversal.
     *
     * @return Node[]
     */
    public function getStubStmts(): array
    {
        if ($this->namespaces) {
            return array_merge(
                $this->namespaces,
                $this->globalNamespace->stmts ? [$this->globalNamespace] : [],
                $this->globalExpressions
            );
        }

        return array_merge(
            $this->globalNamespace->stmts,
            $this->globalExpressions
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
        if ($node instanceof Function_) {
            return $this->needsFunctions && $this->count('functions', "{$namespace}\\{$node->name}");
        }

        if ($node instanceof Class_) {
            return $this->needsClasses && $this->count('classes', "{$namespace}\\{$node->name}");
        }

        if ($node instanceof Interface_) {
            return $this->needsInterfaces && $this->count('interfaces', "{$namespace}\\{$node->name}");
        }

        if ($node instanceof Trait_) {
            return $this->needsTraits && $this->count('traits', "{$namespace}\\{$node->name}");
        }

        if (($this->needsDocumentedGlobals || $this->needsUndocumentedGlobals)
            && $node instanceof Assign
            && $node->var instanceof Variable
            && is_string($node->var->name)
        ) {
            $this->count('globals', $node->var->name);
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
}
