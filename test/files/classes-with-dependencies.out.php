<?php
namespace {
    interface ParentInterface
    {
    }
    interface OtherParentInterface
    {
    }
    interface SubInterface extends \ParentInterface, \OtherParentInterface
    {
    }
    interface OtherInterface
    {
    }
    class ParentClass
    {
    }
    class SubClass extends \ParentClass
    {
    }
    trait OtherParentTrait
    {
    }
    trait OtherTrait
    {
    }
    class SubSubClass extends \SubClass implements \SubInterface, \OtherInterface
    {
        use \OtherParentTrait;
        use \OtherTrait;
    }
    trait ParentTrait
    {
    }
    trait SubTrait
    {
        use \ParentTrait, \OtherParentTrait;
    }
}
namespace A {
    interface ParentInterface
    {
    }
    interface OtherParentInterface
    {
    }
    interface SubInterface extends \A\ParentInterface, \A\OtherParentInterface
    {
    }
    interface OtherInterface
    {
    }
    class ParentClass
    {
    }
    class SubClass extends \A\ParentClass
    {
    }
    trait OtherParentTrait
    {
    }
    trait OtherTrait
    {
    }
    class SubSubClass extends \A\SubClass implements \A\SubInterface, \A\OtherInterface
    {
        use \A\OtherParentTrait;
        use \A\OtherTrait;
    }
    trait ParentTrait
    {
    }
    trait SubTrait
    {
        use \A\ParentTrait, \A\OtherParentTrait;
    }
}
