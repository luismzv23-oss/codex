/** Pure helpers for step-by-step wizards (company onboarding). */

/** Status of a step: 'done', 'skipped' or 'pending'. */
export function stepStatus(steps, step) {
    const value = steps?.[step] ?? steps?.[String(step)];
    return value === 'done' || value === 'skipped' ? value : 'pending';
}

/** Whether the actor can open a step: admins cannot edit superadmin-only steps, and nothing opens before the company exists. */
export function stepLocked(step, {role, companyId, superadminSteps = [1, 2, 3]} = {}) {
    if (!companyId && step > 1) return true;
    return role !== 'superadmin' && superadminSteps.includes(step);
}

/** First step to show: the first pending step the actor may edit, or the last (review) step. */
export function resumeStep(steps, {total = 9, role, companyId, superadminSteps = [1, 2, 3]} = {}) {
    if (!companyId) return 1;
    for (let step = 1; step < total; step++) {
        if (stepLocked(step, {role, companyId, superadminSteps})) continue;
        if (stepStatus(steps, step) === 'pending') return step;
    }
    return total;
}

/** Adds the dependencies of every selected module (by slug), so a module never lacks what it needs. */
export function withDependencies(selectedIds, modules) {
    const bySlug = new Map(modules.map(module => [module.slug, module]));
    const result = new Set(selectedIds);
    let changed = true;
    while (changed) {
        changed = false;
        for (const module of modules) {
            if (!result.has(module.id)) continue;
            for (const slug of module.dependencies || []) {
                const dependency = bySlug.get(slug);
                if (dependency && !result.has(dependency.id)) { result.add(dependency.id); changed = true; }
            }
        }
    }
    return [...result];
}

/** Argentine CUIT check (11 digits, check digit) — same rule as the server. */
export function validCuit(value) {
    const digits = String(value || '').replace(/\D/g, '');
    if (digits.length !== 11) return false;
    const weights = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    const sum = weights.reduce((total, weight, index) => total + Number(digits[index]) * weight, 0);
    let check = 11 - (sum % 11);
    if (check === 11) check = 0;
    return check !== 10 && check === Number(digits[10]);
}
