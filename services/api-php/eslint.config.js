import js from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';
import prettier from 'eslint-config-prettier';

export default [
    js.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    prettier,
    {
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                // Laravel/Inertia globals
                route: 'readonly',
                axios: 'readonly',
                // Browser globals
                window: 'readonly',
                document: 'readonly',
                console: 'readonly',
                setTimeout: 'readonly',
                alert: 'readonly',
                confirm: 'readonly',
            },
        },
        rules: {
            'vue/multi-word-component-names': 'off',
            'vue/require-default-prop': 'off',
            'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
        },
    },
    {
        files: ['resources/js/**/*.test.js'],
        languageOptions: {
            globals: {
                describe: 'readonly',
                it: 'readonly',
                expect: 'readonly',
                vi: 'readonly',
                beforeEach: 'readonly',
                afterEach: 'readonly',
            },
        },
    },
    {
        ignores: [
            'node_modules/**',
            'vendor/**',
            'public/build/**',
            'bootstrap/cache/**',
            'storage/**',
            '*.min.js',
        ],
    },
];
