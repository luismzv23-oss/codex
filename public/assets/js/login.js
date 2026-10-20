/* Alpine CSP enhances the native POST form; credentials never enter component state. */
document.addEventListener('alpine:init', () => {
    Alpine.data('codexLogin', () => ({
        passwordVisible: false,
        submitting: false,
        reducedMotion: true,
        motionQuery: null,
        motionListener: null,
        init() {
            this.motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
            this.reducedMotion = this.motionQuery.matches;
            this.motionListener = (event) => { this.reducedMotion = event.matches; };
            this.motionQuery.addEventListener('change', this.motionListener);
        },
        destroy() { this.motionQuery.removeEventListener('change', this.motionListener); },
        get motionClass() { return this.reducedMotion ? '' : 'login-motion'; },
        get passwordType() { return this.passwordVisible ? 'text' : 'password'; },
        get passwordLabel() { return this.passwordVisible ? 'Ocultar contraseña' : 'Mostrar contraseña'; },
        get passwordAction() { return this.passwordVisible ? 'Ocultar' : 'Mostrar'; },
        get submitLabel() { return this.submitting ? 'Ingresando…' : 'Ingresar al sistema'; },
        togglePassword() { this.passwordVisible = !this.passwordVisible; },
        submit(event) {
            if (this.submitting) { event.preventDefault(); return; }
            // Native constraint validation precedes submit; keep the first POST untouched.
            this.submitting = true;
        },
        resetSubmission() { this.submitting = false; this.passwordVisible = false; },
    }));
});
