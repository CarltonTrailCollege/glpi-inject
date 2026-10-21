module.exports = {
    env: {
        browser: true,
        es2022: true,
        node: true,
    },
    parserOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
    rules: {
        'no-undef': 'error',
        'no-unreachable': 'error',
        'no-unused-vars': ['error', {args: 'none', caughtErrors: 'none'}],
    },
};
