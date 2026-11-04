/**
 * Modules activés côté interface. Doit refléter DOKTA_MODULE_* côté API :
 * en V1, seul l'annuaire est actif.
 */
export const MODULES = {
  saas: process.env.NEXT_PUBLIC_DOKTA_MODULE_SAAS === 'true',
  patient: process.env.NEXT_PUBLIC_DOKTA_MODULE_PATIENT === 'true',
} as const;

export type Module = keyof typeof MODULES;
