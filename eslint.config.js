// eslint.config.js
import js from '@eslint/js';
import globals from 'globals';
import pluginVue from 'eslint-plugin-vue';
import eslintConfigPrettier from 'eslint-config-prettier'; // turns off ESLint rules that conflict with Prettier

export default [
    // Replace .eslintignore
    {
        ignores: [
            'dist/**',
            'build/**',
            'node_modules/**',
            'public/**',
            'resources/js/ziggy.js',
            '**/vendor/**',
            '.eslintrc.cjs',
            'stylelint.config.cjs',
            'vite.config.js',
            'resources/css/app.css',
            'resources/css/Pages/Uploads.scss',
        ],
    },

    // Base JS
    js.configs.recommended,

    // Vue flat preset
    ...pluginVue.configs['flat/essential'],

    // Project-wide language options
    {
        files: ['**/*.{js,mjs,cjs,ts,vue}'],
        languageOptions: {
            globals: globals.browser,
            parserOptions: {
                ecmaVersion: 'latest',
                sourceType: 'module',
            },
        },
        plugins: { vue: pluginVue },
    },

    // Vue rules
    {
        files: ['**/*.vue'],
        rules: {
            'vue/multi-word-component-names': 'off',
        },
    },

    // Keep this LAST so it disables conflicting stylistic rules
    eslintConfigPrettier,
];
