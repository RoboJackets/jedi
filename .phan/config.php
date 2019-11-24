<?php declare(strict_types = 1);

return [
    'target_php_version' => '7.2',

    'directory_list' => [
        '.',
    ],

    'exclude_analysis_directory_list' => [
        'vendor/',
    ],

    'suppress_issue_types' => [
        'PhanPartialTypeMismatchReturn',
        'PhanReadOnlyProtectedProperty',
        'PhanUndeclaredClassMethod',
        'PhanUndeclaredMethod',
        'PhanUnreferencedClass',
        'PhanUnreferencedClosure',
        'PhanUnreferencedConstant',
        'PhanUnreferencedProtectedMethod',
        'PhanUnreferencedProtectedProperty',
        'PhanUnreferencedPublicMethod',
        'PhanUnreferencedPublicProperty',
        'PhanUnreferencedUseNormal',
        'PhanUnusedProtectedMethodParameter',
        'PhanWriteOnlyProtectedProperty',
    ],

    'allow_missing_properties' => false,
    'backward_compatibility_checks' => false,
    'enable_include_path_checks' => true,
    'strict_method_checking' => true,
    'strict_param_checking' => true,
    'strict_return_checking' => true,
    'dead_code_detection' => true,

    'plugins' => [
        'DollarDollarPlugin',
        'AlwaysReturnPlugin',
        'DuplicateArrayKeyPlugin',
        'PregRegexCheckerPlugin',
        'PrintfCheckerPlugin',
        'UnreachableCodePlugin',
        'NonBoolBranchPlugin',
        'NonBoolInLogicalArithPlugin',
        'DuplicateExpressionPlugin',
        'UnusedSuppressionPlugin',
    ],
];
