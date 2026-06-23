import { Helmet } from '@dr.pogodin/react-helmet';
import { ArrowLeft, BookOpen, FileText, Search, Tags } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import {
  documentationCategories,
  documentationStats,
  getArticlesForCategory,
  searchDocumentation,
} from '@/lib/documentation';

import AccountLoadingState from './AccountLoadingState';
import { DocumentationArticleCard } from './documentation-components';

export default function DocumentationCenterPage() {
  const { user, isLoading } = useAuth();
  const [query, setQuery] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('all');

  const articles = useMemo(() => {
    const matches = searchDocumentation(query);

    if (selectedCategory === 'all') return matches;

    return matches.filter((article) => article.category === selectedCategory);
  }, [query, selectedCategory]);

  if (isLoading) {
    return <AccountLoadingState />;
  }

  if (!user) return <Navigate to="/login" replace />;

  const featuredArticles = getArticlesForCategory('getting-started').slice(0, 2);
  const featuredSlugs = new Set(featuredArticles.map((article) => article.slug));
  const isDefaultIndex = query.trim() === '' && selectedCategory === 'all';
  const listedArticles = isDefaultIndex ? articles.filter((article) => !featuredSlugs.has(article.slug)) : articles;

  return (
    <>
      <Helmet>
        <title>Documentation Center | The Blend Battlegrounds</title>
        <meta name="description" content="BlendBeats user documentation for account, memberships, affiliate program, DJ features, marketplace, community, and FAQs." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a]">
        <section className="border-b border-[#1a1a1a] px-4 py-12 lg:px-8 lg:py-16">
          <div className="container mx-auto max-w-6xl">
            <Link
              to="/account"
              className="mb-10 inline-flex h-11 items-center gap-2 border border-[#333333] px-4 text-xs font-bold uppercase text-[#dddddd] transition-colors hover:border-primary hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              <ArrowLeft size={15} />
              Account
            </Link>

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-end">
              <div>
                <p className="mb-3 text-xs font-bold uppercase text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                  Account / Documentation
                </p>
                <h1
                  className="text-4xl uppercase leading-none text-white sm:text-6xl lg:text-7xl"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  Documentation Center
                </h1>
                <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
                  User help for BlendBeats account workflows, memberships, affiliate credits, DJ tools, marketplace activity, community features, and common questions.
                </p>
              </div>

              <div className="grid grid-cols-3 border border-[#303030] bg-[#111111]">
                <div className="border-r border-[#262626] p-4">
                  <p className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    {documentationStats.categories}
                  </p>
                  <p className="mt-1 text-[10px] font-bold uppercase text-[#888888]">Categories</p>
                </div>
                <div className="border-r border-[#262626] p-4">
                  <p className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    {documentationStats.articles}
                  </p>
                  <p className="mt-1 text-[10px] font-bold uppercase text-[#888888]">Articles</p>
                </div>
                <div className="p-4">
                  <p className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    {documentationStats.active}
                  </p>
                  <p className="mt-1 text-[10px] font-bold uppercase text-[#888888]">Active</p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 py-10 lg:px-8">
          <div className="container mx-auto max-w-6xl">
            <div className="mb-6 grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
              <div className="flex gap-2 overflow-x-auto pb-1">
                <button
                  type="button"
                  onClick={() => setSelectedCategory('all')}
                  className={`inline-flex h-11 shrink-0 items-center gap-2 border px-4 text-xs font-bold uppercase transition-colors ${
                    selectedCategory === 'all' ? 'border-primary bg-primary text-white' : 'border-[#333333] text-[#dddddd] hover:border-primary hover:text-primary'
                  }`}
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  <Tags size={15} />
                  All
                </button>
                {documentationCategories.map((category) => (
                  <button
                    key={category.slug}
                    type="button"
                    onClick={() => setSelectedCategory(category.slug)}
                    className={`inline-flex h-11 shrink-0 items-center border px-4 text-xs font-bold uppercase transition-colors ${
                      selectedCategory === category.slug ? 'border-primary bg-primary text-white' : 'border-[#333333] text-[#dddddd] hover:border-primary hover:text-primary'
                    }`}
                    style={{ fontFamily: 'var(--font-heading)' }}
                  >
                    {category.title}
                  </button>
                ))}
              </div>

              <label className="relative block">
                <Search className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[#777777]" size={18} />
                <input
                  value={query}
                  onChange={(event) => setQuery(event.target.value)}
                  placeholder="Search documentation"
                  className="h-11 w-full border border-[#333333] bg-[#111111] pl-10 pr-3 text-sm text-white outline-none transition-colors placeholder:text-[#666666] focus:border-primary"
                />
              </label>
            </div>

            {isDefaultIndex && (
              <div className="mb-8 grid gap-4 md:grid-cols-2">
                {featuredArticles.map((article) => (
                  <DocumentationArticleCard key={article.slug} article={article} />
                ))}
              </div>
            )}

            <div className="mb-6 flex items-center gap-3">
              <BookOpen size={20} className="text-primary" />
              <h2 className="text-3xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                Articles
              </h2>
              <span className="text-sm text-[#777777]">{listedArticles.length}</span>
            </div>

            {listedArticles.length > 0 ? (
              <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {listedArticles.map((article) => (
                  <DocumentationArticleCard key={article.slug} article={article} />
                ))}
              </div>
            ) : (
              <div className="border border-[#2a2a2a] bg-[#111111] p-8 text-center">
                <FileText className="mx-auto text-[#FFB800]" size={30} />
                <h2 className="mt-5 text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                  No Articles Found
                </h2>
                <p className="mt-3 text-sm leading-6 text-[#888888]">Try another search term or category.</p>
              </div>
            )}
          </div>
        </section>
      </main>
    </>
  );
}
