(() => {
    const { createApp } = Vue;

    createApp({
        data() {
            return {
                api: {
                    statusLabel: 'Consultando...',
                    payload: 'Cargando respuesta de la API interna...',
                },
            };
        },
        mounted() {
            this.fetchHealth();
        },
        methods: {
            fetchHealth() {
                $.getJSON(window.CodexApp.apiHealthUrl)
                    .done((response) => {
                        this.api.statusLabel = 'Disponible';
                        this.api.payload = JSON.stringify(response, null, 2);
                    })
                    .fail(() => {
                        this.api.statusLabel = 'Sin respuesta';
                        this.api.payload = JSON.stringify({
                            status: 'error',
                            message: 'No se pudo consultar la API interna.',
                        }, null, 2);
                    });
            },
        },
    }).mount('#app');
})();
