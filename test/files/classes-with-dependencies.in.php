<?php
// This file is invalid PHP due to the order of classes.  It's meant to mimic
// the order classes might be evaluated in by our parser were they loaded in
// from different files.
namespace {
    class SubSubClass extends SubClass implements SubInterface, OtherInterface
    {
        use OtherParentTrait;
        use OtherTrait;
    }
    class SubClass extends ParentClass
    {
    }
    class ParentClass
    {
    }
    interface SubInterface extends ParentInterface, OtherParentInterface
    {
    }
    interface ParentInterface
    {
    }
    interface OtherParentInterface
    {
    }
    interface OtherInterface
    {
    }
    trait SubTrait
    {
        use ParentTrait, OtherParentTrait;
    }
    trait ParentTrait
    {
    }
    trait OtherParentTrait
    {
    }
    trait OtherTrait
    {
    }
}
namespace A {
    class SubSubClass extends SubClass implements SubInterface, OtherInterface
    {
        use OtherParentTrait;
        use OtherTrait;
    }
    class SubClass extends ParentClass
    {
    }
    class ParentClass
    {
    }
    interface SubInterface extends ParentInterface, OtherParentInterface
    {
    }
    interface ParentInterface
    {
    }
    interface OtherParentInterface
    {
    }
    interface OtherInterface
    {
    }
    trait SubTrait
    {
        use ParentTrait, OtherParentTrait;
    }
    trait ParentTrait
    {
    }
    trait OtherParentTrait
    {
    }
    trait OtherTrait
    {
    }
}
