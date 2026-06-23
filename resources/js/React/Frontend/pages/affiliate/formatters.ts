import type { AffiliateApiError } from '@/lib/affiliate';

export function formatDate(value: string | null): string {
  if (!value) return 'Not Set';

  return new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(new Date(value));
}

export function firstError(error: AffiliateApiError): string {
  const fieldError = Object.values(error.errors)[0]?.[0];

  return fieldError || error.message;
}

export function numberLabel(value: number): string {
  return value.toLocaleString();
}

export function percentLabel(value: number): string {
  return `${value.toLocaleString(undefined, { maximumFractionDigits: 2 })}%`;
}

export function statusLabel(status: string): string {
  return status
    .split('_')
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

export function titleLabel(value: string | null | undefined): string {
  if (!value) return 'Not Set';

  return statusLabel(value);
}
