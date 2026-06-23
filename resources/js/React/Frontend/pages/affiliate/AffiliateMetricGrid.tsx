type AffiliateMetric = {
  label: string;
  note?: string;
  value: string;
};

type AffiliateMetricGridProps = {
  metrics: AffiliateMetric[];
  valueSize?: 'large' | 'medium';
};

export default function AffiliateMetricGrid({ metrics, valueSize = 'large' }: AffiliateMetricGridProps) {
  const valueClassName = valueSize === 'large'
    ? 'mt-2 text-3xl uppercase text-white'
    : 'mt-2 text-xl font-semibold text-[#eeeeee]';

  return (
    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      {metrics.map((metric) => (
        <div key={metric.label} className="border border-[#303030] bg-[#080808] p-4">
          <p className="text-xs font-bold uppercase text-[#777777]">{metric.label}</p>
          <p className={valueClassName} style={valueSize === 'large' ? { fontFamily: 'var(--font-heading)' } : undefined}>
            {metric.value}
          </p>
          {metric.note && <p className="mt-2 text-xs text-[#888888]">{metric.note}</p>}
        </div>
      ))}
    </div>
  );
}
