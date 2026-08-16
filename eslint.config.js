import js from '@eslint/js';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';

/**
 * Correctness only — no style rules.
 *
 * Formatting is Prettier's job, and a linter that also argues about quotes buries the
 * findings that matter. What is here catches things that break at runtime: a hook
 * called conditionally, a missing dependency, an undefined variable, a key-less list.
 *
 * ESLint is pinned to 9 because eslint-plugin-react does not support 10 yet.
 */
export default [
    {
        ignores: [
            'public/**',
            'node_modules/**',
            'vendor/**',
            'resources/assets/**',
            'storage/**',
            'bootstrap/**',
        ],
    },

    js.configs.recommended,

    {
        files: ['resources/js/**/*.{js,jsx}'],

        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
                // Provided by Ziggy and the vendored theme's global scripts.
                route: 'readonly',
                Echo: 'readonly',
            },
            parserOptions: {
                ecmaFeatures: { jsx: true },
            },
        },

        plugins: {
            react,
            'react-hooks': reactHooks,
        },

        settings: {
            react: { version: 'detect' },
        },

        rules: {
            ...react.configs.flat.recommended.rules,
            ...reactHooks.configs.recommended.rules,

            // React 17+ JSX transform: importing React is not required.
            'react/react-in-jsx-scope': 'off',

            // The project uses plain JSX with no prop-types and no TypeScript. Turning
            // this on would flag every component in the codebase and teach everyone to
            // ignore the linter.
            'react/prop-types': 'off',

            // Nine pre-existing hits, all the same shape: a guarded setState that opens
            // a submenu or syncs a panel when a derived prop changes. That is intentional,
            // not a cascade — so this is a warning, deliberately visible rather than
            // silenced, and new code gets nudged away from the pattern.
            'react-hooks/set-state-in-effect': 'warn',

            // Unused variables usually mean a half-finished edit; leading underscore is
            // the escape hatch for deliberately ignored arguments.
            'no-unused-vars': ['warn', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
        },
    },
];
