import { ArrowRight } from 'lucide-react';
import { Link } from 'react-router-dom';

import {
  documentationStatusLabel,
  getDocumentationCategory,
  type DocumentationArticle,
  type DocumentationArticleStatus,
} from '@/lib/documentation';

const statusClasses: Record<DocumentationArticleStatus, string> = {
  active: 'border-primary/40 bg-primary/10 text-primary',
  foundation: 'border-[#FFB800]/50 bg-[#FFB800]/10 text-[#FFB800]',
  future: 'border-[#555555] bg-[#181818] text-[#bbbbbb]',
};

export function DocumentationStatusBadge({ status }: { status: DocumentationArticleStatus }) {
  return (
    <span className={`inline-flex h-8 items-center border px-3 text-[10px] font-bold uppercase ${statusClasses[status]}`}>
      {documentationStatusLabel(status)}
    </span>
  );
}

export function DocumentationArticleCard({ article }: { article: DocumentationArticle }) {
  const category = getDocumentationCategory(article.category);

  return (
    <Link
      to={`/account/docs/${article.slug}`}
      className="grid min-h-56 border border-[#2a2a2a] bg-[#111111] p-5 transition-colors hover:border-primary"
    >
      <div>
        <div className="mb-5 flex flex-wrap items-center gap-2">
          <span className="inline-flex h-8 items-center border border-[#333333] px-3 text-[10px] font-bold uppercase text-[#aaaaaa]">
            {category?.title ?? 'Documentation'}
          </span>
          <DocumentationStatusBadge status={article.status} />
        </div>
        <h3 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
          {article.title}
        </h3>
        <p className="mt-3 text-sm leading-6 text-[#999999]">{article.summary}</p>
      </div>
      <span
        className="mt-6 inline-flex h-11 items-center justify-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase text-[#dddddd]"
        style={{ fontFamily: 'var(--font-heading)' }}
      >
        Open
        <ArrowRight size={15} />
      </span>
    </Link>
  );
}
