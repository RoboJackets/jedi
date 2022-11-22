<?php

declare(strict_types=1);

return [
    'target_php_version' => '8.1',

    'directory_list' => [
        '.',
    ],

    'exclude_analysis_directory_list' => [
        'vendor/',
        'bootstrap/cache/',
    ],

    'suppress_issue_types' => [
        'PhanCompatibleObjectTypePHP71',
        'PhanInvalidFQSENInCallable',
        'PhanPartialTypeMismatchReturn',
        'PhanPossiblyFalseTypeArgumentInternal',
        'PhanReadOnlyProtectedProperty',
        'PhanUndeclaredClassMethod',
        'PhanUndeclaredFunctionInCallable',
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
        'PhanUnusedPublicMethodParameter',
        'PhanUnusedPublicNoOverrideMethodParameter',
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
