import { createColumnMap, defineDomainRegistry } from './helpers.js';
import { BankAccountColumnRegistry } from './domains/bank-account.js';
import { CardColumnRegistry } from './domains/card.js';
import { ClientColumnRegistry } from './domains/client.js';
import { CodeColumnRegistry } from './domains/code.js';
import { ProjectColumnRegistry } from './domains/project.js';
import { WorkTeamColumnRegistry } from './domains/work-team.js';

const DOMAIN_REGISTRIES = Object.freeze({
    'bank-account': BankAccountColumnRegistry,
    card: CardColumnRegistry,
    client: ClientColumnRegistry,
    code: CodeColumnRegistry,
    project: ProjectColumnRegistry,
    'work-team': WorkTeamColumnRegistry,
});

export function getColumnMetaRegistry(domain) {
    const resolvedDomain = String(domain ?? '').trim();
    const registry = DOMAIN_REGISTRIES[resolvedDomain];
    if (!registry) {
        throw new Error(`ColumnMetaRegistry domain not found: ${resolvedDomain}`);
    }

    return registry;
}

export function getColumnMetaList(domain) {
    return getColumnMetaRegistry(domain).columns;
}

export function getColumnMetaMap(domain) {
    return createColumnMap(getColumnMetaList(domain));
}

export function getColumnMeta(domain, key) {
    return getColumnMetaMap(domain)[key] ?? null;
}

export function listColumnMetaDomains() {
    return Object.keys(DOMAIN_REGISTRIES);
}

export function defineRuntimeColumnRegistry(domain, columns) {
    return defineDomainRegistry(domain, columns);
}
