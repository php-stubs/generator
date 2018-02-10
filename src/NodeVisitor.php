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

        // These are the only nodes we need to parse the children of, unless in
        // the future we wish to parse function or method bodies for constant or
        // global declarations.  Also, no reason to bother traversing the
        // children of declarations which we won't include anyway.
        if ($node instanceof Namespace_
            || ($this->needsClasses && $node instanceof Class_)
            || ($this->needsInterfaces && $node instanceof Interface_)
            || ($this->needsTraits && $node instanceof Trait_)
        ) {
            return;
        }

        if ($node instanceof Function_ || $node instanceof ClassMethod) {
            // We can just delete function or method bodies for our stubs.  In
            // the future we may want to parse them for constant definitions or
            // the like.
            if ($node->stmts) {
                $node->stmts = [];
            }
        } elseif ($node instanceof Assign) {
            // Since we don't parse any the bodies of any statements which can
            // hold variable assignments---other than namespaces---we know these
            // assigns are for globals.  Check if we are assigning to `$GLOBALS`
            // with a simple string.  If so, convert it to a normal variable
            // assignment.
            if (count($this->stack) === 1
                && $node->var instanceof ArrayDimFetch
                && $node->var->var instanceof Variable
                && $node->var->var->name === 'GLOBALS'
                && $node->var->dim instanceof String_
                && preg_match('/[a-zA-Z_]+[a-zA-Z0-9_]*/', $node->var->dim->value)
            ) {
                $node->var = new Variable($node->var->dim->value);
            }
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
                return $first;
            }
        }

        return NodeTraverser::DONT_TRAVERSE_CHILDREN;
    }

    public function leaveNode(Node $node)
    {
        array_pop($this->stack);
        $parent = $this->stack[count($this->stack) - 1] ?? null;

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

        if ($this->needsNode($node)) {
            if ($parent) {
                // If we're here, `$parent` is a namespace.  Let's just keep the
                // `$node` around in `$parent->stmts`.
                return;
            } elseif ($node instanceof Stmt) {
                // Anything other than a namespace which doesn't have a parent
                // node must belong in the global namespace. We can still remove
                // the `$node` from the current list of statements since we're
                // stashing it for later no matter what.
                // HACK: technically only statements should be added.
                // assert($node instanceof Stmt, 'Only statements should be added to the top-level namespace.');
                $this->globalNamespace->stmts[] = $node;
            } else {
                $this->globalExpressions[] = $node;
            }
        }

        // Any other top level junk we are happy to remove.
        return NodeTraverser::REMOVE_NODE;
    }

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

    public function getCounts(): array
    {
        return $this->counts;
    }

    /**
     * Determines if we should keep the given `$node` in `$this->leaveNode()`.
     *
     * @param Node $node
     *
     * @return bool
     */
    private function needsNode(Node $node): bool
    {
        if ($node instanceof Function_) {
            return $this->needsFunctions && $this->count('functions', $node->name);
        }

        if ($node instanceof Class_) {
            return $this->needsClasses && $this->count('classes', $node->name);
        }

        if ($node instanceof Interface_) {
            return $this->needsInterfaces && $this->count('interfaces', $node->name);
        }

        if ($node instanceof Trait_) {
            return $this->needsTraits && $this->count('traits', $node->name);
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
