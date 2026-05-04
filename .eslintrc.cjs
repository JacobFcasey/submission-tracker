require('@rushstack/eslint-patch/modern-module-resolution');

module.exports = {
    root: true,
    env: { browser: true, node: true, es2021: true },
    extends: [
        'eslint:recommended',
        'plugin:vue/vue3-recommended',
        '@vue/eslint-config-prettier', // disables style rules that clash with Prettier
    ],
    parserOptions: { ecmaVersion: 'latest', sourceType: 'module' },
    rules: {
        'vue/multi-word-component-names': 'off',
    },
    ignorePatterns: [
        'vendor/**',
        'public/build/**',
        'node_modules/**',
        'storage/**',
        'bootstrap/cache/**',
        'resources/css/app.css',
        'resources/css/Pages/Uploads.scss',
    ],
};
