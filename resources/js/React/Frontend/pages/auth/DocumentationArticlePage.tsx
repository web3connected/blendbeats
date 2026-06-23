import { Helmet } from '@dr.pogodin/react-helmet';
import { ArrowLeft, ArrowRight, BookOpen, CheckCircle2, ExternalLink, FileText, Tag } from 'lucide-react';
import { Link, Navigate, useParams } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import {
  documentationStatusLabel,
  getDocumentationArticle,
  getDocumentationCategory,
  getRelatedDocumentationArticles,
} from '@/lib/documentation';

import AccountLoadingState from './AccountLoadingState';
import { DocumentationStatusBadge } from './documentation-components';

function ActionLink({ href }: { href: string }) {
  const className = 'mt-6 inline-flex h-11 w-full items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase text-white transition-colors hover:bg-[#d91515]';
  const style = { fontFamily: 'var(--font-heading)' };

  if (href.startsWith('/news')) {
    return (
      <a href={href} className={className} style={style}>
        Open Page
        <ExternalLink size={15} />
      </a>
    );
  }

  return (
    <Link to={href} className={className} style={style}>
      Open Page
      <ArrowRight size={15} />
    </Link>
  );
}

export default function DocumentationArticlePage() {
  const { user, isLoading } = useAuth();
  const { slug = '' } = useParams();
  const article = getDocumentationArticle(slug);

  if (isLoading) {
    return <AccountLoadingState />;
  }

  if (!user) return <Navigate to="/login" replace />;

  if (!article) return <Navigate to="/account/docs" replace />;

  const category = getDocumentationCategory(article.category);
  const relatedArticles = getRelatedDocumentationArticles(article);

  return (
    <>
      <Helmet>
        <title>{article.title} | Documentation Center</title>
        <meta name="description" content={article.summary} />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-12 lg:px-8 lg:py-16">
          <div className="container mx-auto max-w-5xl">
            <Link
              to="/account/docs"
              className="mb-10 inline-flex h-11 items-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <ArrowLeft size={15} />
              Documentation
            </Link>

            <div className="mb-5 flex flex-wrap items-center gap-2">
              <span className="inline-flex h-8 items-center border border-[#333333] px-3 text-[10px] font-bold uppercase text-[#aaaaaa]">
                {category?.title ?? 'Documentation'}
              </span>
              <DocumentationStatusBadge status={article.status} />
            </div>

            <h1
              className="text-4xl uppercase leading-none text-white sm:text-6xl lg:text-7xl"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              {article.title}
            </h1>
            <p className="mt-5 max-w-3xl text-base leading-7 text-[#aaaaaa]">{article.summary}</p>
          </div>
        </section>

        <section className="px-4 py-10 lg:px-8">
          <div className="container mx-auto grid max-w-5xl gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
            <div className="grid gap-4">
              {article.sections.map((section) => (
                <article key={section.title} className="border border-[#2a2a2a] bg-[#111111] p-5">
                  <div className="flex items-start gap-3">
                    <CheckCircle2 className="mt-1 shrink-0 text-[#FFB800]" size={20} />
                    <div>
                      <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                        {section.title}
                      </h2>
                      <p className="mt-3 text-sm leading-7 text-[#aaaaaa]">{section.body}</p>
                      {section.bullets && (
                        <ul className="mt-4 space-y-2 text-sm leading-6 text-[#999999]">
                          {section.bullets.map((bullet) => (
                            <li key={bullet} className="flex gap-2">
                              <span className="mt-2 h-1.5 w-1.5 shrink-0 bg-primary" />
                              <span>{bullet}</span>
                            </li>
                          ))}
                        </ul>
                      )}
                    </div>
                  </div>
                </article>
              ))}
            </div>

            <aside className="grid h-fit gap-5">
              <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                <BookOpen className="text-primary" size={24} />
                <h2 className="mt-5 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  Article Details
                </h2>
                <dl className="mt-5 grid gap-3 text-sm">
                  <div>
                    <dt className="text-[10px] font-bold uppercase text-[#777777]">Category</dt>
                    <dd className="mt-1 text-[#dddddd]">{category?.title ?? 'Documentation'}</dd>
                  </div>
                  <div>
                    <dt className="text-[10px] font-bold uppercase text-[#777777]">Status</dt>
                    <dd className="mt-1 text-[#dddddd]">{documentationStatusLabel(article.status)}</dd>
                  </div>
                  <div>
                    <dt className="text-[10px] font-bold uppercase text-[#777777]">Updated</dt>
                    <dd className="mt-1 text-[#dddddd]">{article.updatedAt}</dd>
                  </div>
                </dl>
                {article.manageHref && <ActionLink href={article.manageHref} />}
              </section>

              {relatedArticles.length > 0 && (
                <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                  <FileText className="text-[#FFB800]" size={22} />
                  <h2 className="mt-5 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    Related
                  </h2>
                  <div className="mt-4 grid gap-2">
                    {relatedArticles.map((related) => (
                      <Link
                        key={related.slug}
                        to={`/account/docs/${related.slug}`}
                        className="flex items-center justify-between gap-3 border border-[#2a2a2a] bg-[#080808] px-3 py-3 text-sm text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
                      >
                        <span>{related.title}</span>
                        <ArrowRight size={14} />
                      </Link>
                    ))}
                  </div>
                </section>
              )}

              <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                <Tag className="text-primary" size={22} />
                <h2 className="mt-5 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  Keywords
                </h2>
                <div className="mt-4 flex flex-wrap gap-2">
                  {article.keywords.slice(0, 8).map((keyword) => (
                    <span key={keyword} className="border border-[#333333] px-3 py-1 text-xs text-[#aaaaaa]">
                      {keyword}
                    </span>
                  ))}
                </div>
              </section>
            </aside>
          </div>
        </section>
      </main>
    </>
  );
}
