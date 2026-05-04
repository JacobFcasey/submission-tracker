const Ziggy = {
    url: 'http:\/\/localhost',
    port: null,
    defaults: {},
    routes: {
        dashboard: { uri: 'dashboard', methods: ['GET', 'HEAD'] },
        'uploads.index': { uri: 'uploads', methods: ['GET', 'HEAD'] },
        'uploads.store': { uri: 'uploads', methods: ['POST'] },
        'uploads.destroy': {
            uri: 'uploads\/{upload}',
            methods: ['DELETE'],
            parameters: ['upload'],
            bindings: { upload: 'id' },
        },
        'uploads.history': { uri: 'uploads\/history', methods: ['GET', 'HEAD'] },
        'uploads.export': { uri: 'uploads\/export', methods: ['GET', 'HEAD'] },
        'deadlines.municipalities.index': {
            uri: 'deadlines\/municipalities',
            methods: ['GET', 'HEAD'],
        },
        'deadlines.municipalities.store': { uri: 'deadlines\/municipalities', methods: ['POST'] },
        'deadlines.municipalities.destroy': {
            uri: 'deadlines\/municipalities\/{id}',
            methods: ['DELETE'],
            parameters: ['id'],
        },
        'municipalities.companies': {
            uri: 'municipalities\/{id}\/companies',
            methods: ['GET', 'HEAD'],
            parameters: ['id'],
        },
        'deadlines.companies': { uri: 'deadlines\/companies', methods: ['GET', 'HEAD'] },
        'analytics.index': { uri: 'analytics', methods: ['GET', 'HEAD'] },
        'settings.index': { uri: 'settings', methods: ['GET', 'HEAD'] },
        'storage.local': {
            uri: 'storage\/{path}',
            methods: ['GET', 'HEAD'],
            wheres: { path: '.*' },
            parameters: ['path'],
        },
    },
};
if (typeof window !== 'undefined' && typeof window.Ziggy !== 'undefined') {
    Object.assign(Ziggy.routes, window.Ziggy.routes);
}
export { Ziggy };
