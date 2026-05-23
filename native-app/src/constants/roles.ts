export const ROLE_PRIORITY = [
  'admin',
  'manager',
  'pic',
  'student',
  'staff',
  'external',
] as const;

export type UserRole = (typeof ROLE_PRIORITY)[number] | 'user';

export function isStudentRole(role: string): boolean {
  return role === 'student' || role === 'staff';
}

export function isExternalRole(role: string): boolean {
  return role === 'external';
}

export function isOperationalRole(role: string): boolean {
  return ['admin', 'manager', 'pic'].includes(role);
}
