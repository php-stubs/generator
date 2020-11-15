<?php
namespace A {
    use B\B as C;
    use D;

    class A extends C implements D
    {
        const A = C::A * 5;
        const B = GLOBAL_FALLBACK;
        const C = \GLOBAL_EXPLICIT;

        public function a($a = C::A): C
        {
        }
    }

    function a()
    {
        return '';
    }
}
namespace {
    use const B\B;
    use D\D;

    class A extends C implements D
    {
        const A = B;
        const B = GLOBAL_FALLBACK;
        const C = \GLOBAL_EXPLICIT;
    }

    function a()
    {
        return '';
    }

    $a = 'a';
}
