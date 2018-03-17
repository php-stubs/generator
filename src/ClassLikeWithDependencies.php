<?php
namespace StubsGenerator;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUse;

/**
 * @internal
 */
class ClassLikeWithDependencies
{
    /** @var ClassLike */
    public $node;

    /** @var string[] */
    public $dependencies = [];

    /** @var string */
    public $namespace;

    /** @var string */
    public $fullyQualifiedName;

    public function __construct(ClassLike $node, string $namespace)
    {
        $this->node = $node;
        $this->namespace = $namespace;
        $this->fullyQualifiedName = $this->globalQualify($namespace . '\\' . ($node->name ?: ''));

        // NOTE: We expect all names to be fully qualified.
        if ($node instanceof Class_) {
            // Register any interfaces.
            $this->dependencies = $this->namesToStrings($node->implements);

            // Register parent class.
            if ($node->extends) {
                $this->dependencies[] = $this->globalQualify($node->extends->toString());
            }
        } elseif ($node instanceof Interface_) {
            // Register parent interfaces.
            $this->dependencies = $this->namesToStrings($node->extends);
        }

        if ($node instanceof Class_ || $node instanceof Trait_) {
            // Register any trait uses.
            foreach ($node->stmts as $stmt) {
                if ($stmt instanceof TraitUse) {
                    array_push($this->dependencies, ...$this->namesToStrings($stmt->traits));
                }
            }
        }
    }

    /**
     * @param Name[] $names
     * @return string[]
     */
    private function namesToStrings(array $names): array
    {
        return array_map(function (Name $name): string {
            return $this->globalQualify($name->toString());
        }, $names);
    }

    /**
     * @param string $name
     * @return string
     */
    private function globalQualify(string $name): string
    {
        return '\\' . ltrim($name, '\\');
    }
}
