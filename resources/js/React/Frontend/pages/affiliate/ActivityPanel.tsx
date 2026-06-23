import type { LucideIcon } from 'lucide-react';

import type { AffiliateActivityItem } from '@/lib/affiliate';

import { formatDate } from './formatters';

type ActivityPanelProps = {
  emptyText: string;
  icon: LucideIcon;
  iconClassName: string;
  items: AffiliateActivityItem[];
  title: string;
};

export default function ActivityPanel({ emptyText, icon: Icon, iconClassName, items, title }: ActivityPanelProps) {
  return (
    <section className="border border-[#303030] bg-[#080808] p-4">
      <div className="mb-4 flex items-center gap-3">
        <Icon className={iconClassName} size={18} />
        <h3 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
          {title}
        </h3>
      </div>
      <div className="grid gap-2">
        {items.length > 0 ? items.slice(0, 8).map((item, index) => (
          <div key={`${item.type}-${item.occurred_at ?? index}`} className="border border-[#303030] bg-[#111111] p-3">
            <p className="text-sm font-semibold text-white">{item.label}</p>
            <p className="mt-1 text-xs leading-5 text-[#888888]">{item.description}</p>
            <p className="mt-2 text-xs text-[#666666]">{formatDate(item.occurred_at)}</p>
          </div>
        )) : (
          <p className="text-sm leading-6 text-[#888888]">{emptyText}</p>
        )}
      </div>
    </section>
  );
}
