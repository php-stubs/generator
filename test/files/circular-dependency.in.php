We don't care about generating good stubs for invalid PHP, but we do want to
avoid infinite recursion.
<?php
class A extends B
{
}
class B extends A
{
}
class C extends C
{
}
