import {test} from 'node:test';
import assert from 'node:assert/strict';
import {stepStatus, stepLocked, resumeStep, withDependencies, validCuit} from '../../public/assets/js/wizard-steps.mjs';

test('A new company always starts at the company step and nothing else opens before it exists', () => {
    assert.equal(resumeStep({}, {role: 'superadmin', companyId: null}), 1);
    assert.equal(stepLocked(2, {role: 'superadmin', companyId: null}), true);
});

test('Superadmin resumes at the first pending step; skipped optional steps count as handled', () => {
    const steps = {1: 'done', 2: 'done', 3: 'done', 4: 'done', 5: 'done', 6: 'done', 7: 'skipped'};
    assert.equal(stepStatus(steps, 7), 'skipped');
    assert.equal(resumeStep(steps, {role: 'superadmin', companyId: 'c'}), 8);
    assert.equal(resumeStep({1: 'done'}, {role: 'superadmin', companyId: 'c'}), 2);
});

test('Company admin cannot edit superadmin steps and resumes from step 4', () => {
    assert.equal(stepLocked(2, {role: 'admin', companyId: 'c'}), true);
    assert.equal(stepLocked(5, {role: 'admin', companyId: 'c'}), false);
    assert.equal(resumeStep({}, {role: 'admin', companyId: 'c'}), 4);
    const all = {1: 'done', 2: 'done', 3: 'done', 4: 'done', 5: 'done', 6: 'done', 7: 'done', 8: 'done'};
    assert.equal(resumeStep(all, {role: 'admin', companyId: 'c'}), 9);
});

test('Selecting a module also selects the modules it depends on (transitively)', () => {
    const modules = [{id: 'inv', slug: 'inventario', dependencies: []}, {id: 'ven', slug: 'ventas', dependencies: ['inventario']},
        {id: 'com', slug: 'comercial', dependencies: ['ventas']}];
    assert.deepEqual(withDependencies(['com'], modules).sort(), ['com', 'inv', 'ven']);
});

test('CUIT validation matches the server check digit rule', () => {
    assert.equal(validCuit('30-71916712-4'), true);
    assert.equal(validCuit('30707943463'), true);
    assert.equal(validCuit('30707943460'), false);
    assert.equal(validCuit('123'), false);
});
