module.exports = {
    ignores: [
        '**/node_modules/**',
        '**/dist/**',
        '**/build/**',
        '**/public/**',
        'resources/js/ziggy.js',
        'resources/css/app.css',
        'resources/css/Pages/Uploads.scss',
    ],
    rules: {
        'media-feature-range-notation': 'context',
        'color-function-notation': null,
        'alpha-value-notation': 'percentage',
        'color-function-alias-notation': null,

        // Stay strict but sane
        'no-descending-specificity': true,
        'at-rule-empty-line-before': [
            'always',
            { except: ['first-nested'], ignore: ['after-comment'] },
        ],
        'rule-empty-line-before': [
            'always',
            { except: ['first-nested'], ignore: ['after-comment'] },
        ],

        // Vue SFC style blocks often need this relaxed
        'selector-class-pattern': null,
    },
    extends: [
        'stylelint-config-standard',
        'stylelint-config-recommended-vue',
        'stylelint-config-tailwindcss',
    ],
    ignoreFiles: [
        '**/vendor/**',
        '**/node_modules/**',
        '**/public/build/**',
        '**/storage/**',
        '**/bootstrap/cache/**',
        'resources/css/**',
    ],
    overrides: [{ files: ['**/*.vue', '**/*.html'], customSyntax: 'postcss-html' }],
};
