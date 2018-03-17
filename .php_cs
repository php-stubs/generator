<?php
// http://cs.sensiolabs.org/#usage
$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/test')
    ->in(__DIR__ . '/bin')
;

return PhpCsFixer\Config::create()
    ->setRules([
        // If you're curious what these do, use `php-cs-fixer describe <key>`
        '@PSR1' => true,
        '@PSR2' => true,
        'align_multiline_comment' => ['comment_type' => 'phpdocs_only'],
        'array_syntax' => ['syntax' => 'short'],
        'cast_spaces' => ['space' => 'single'],
        'concat_space' => ['spacing' => 'one'],
        'declare_equal_normalize' => ['space' => 'single'],
        'function_typehint_space' => true,
        'include' => true,
        'linebreak_after_opening_tag' => true,
        // 'list_syntax' => ['syntax' => 'short'], Requires PHP 7.1
        'magic_constant_casing' => true,
        'method_separation' => true,
        'native_function_casing' => true,
        'new_with_braces' => true,
        'no_blank_lines_after_class_opening' => true,
        'no_blank_lines_before_namespace' => true,
        'no_empty_phpdoc' => true,
        'no_empty_statement' => true,
        'no_leading_import_slash' => true,
        'no_short_bool_cast' => true,
        'no_spaces_around_offset' => true,
        'no_unused_imports' => true,
        'normalize_index_brace' => true,
        'not_operator_with_successor_space' => false,
        'ordered_imports' => ['sortAlgorithm' => 'alpha'],
        'phpdoc_indent' => true,
        'phpdoc_order' => true,
        'phpdoc_scalar' => true,
        'phpdoc_trim' => true,
        'phpdoc_types' => true,
        'phpdoc_var_without_name' => true,
        'self_accessor' => false,
        'semicolon_after_instruction' => true,
        'short_scalar_cast' => true,
        'single_quote' => true,
        'ternary_operator_spaces' => true,
        'whitespace_after_comma_in_array' => true,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/.php_cs.cache')
;
