import { Helmet } from '@dr.pogodin/react-helmet';
import {
  Heart,
  MessageCircle,
  Radio,
  Repeat2,
  Bookmark,
  Send,
  Share2,
  Sparkles,
  Users,
} from 'lucide-react';
import { type FormEvent, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';

import { useAuth } from '@/components/auth/AuthProvider';
import FeaturedDjSlotCard from '@/components/featured/FeaturedDjSlotCard';
import { djLoungeTrends } from '@/config/djLounge';
import { useFeaturedDjs } from '@/hooks/use-featured-djs';
import {
  createDjLoungePost,
  createDjLoungeReply,
  type DjLoungePost,
  type DjLoungeReply,
  type DjLoungeStats,
  getDjLoungeFeed,
  getDjLoungePosts,
  toggleDjLoungePostBookmark,
  toggleDjLoungePostReaction,
  toggleDjLoungePostRepost,
} from '@/lib/dj-lounge';

function LoungeAvatar({
  src,
  initial,
  alt,
  className,
}: {
  src?: string | null;
  initial: string;
  alt: string;
  className: string;
}) {
  if (src) {
    return <img src={src} alt={alt} className={`${className} object-cover`} />;
  }

  return (
    <div className={`${className} flex items-center justify-center bg-primary font-black uppercase text-white`}>
      {initial}
    </div>
  );
}

function ReplyComposer({
  placeholder,
  isSubmitting,
  onSubmit,
}: {
  placeholder: string;
  isSubmitting: boolean;
  onSubmit: (body: string) => Promise<void>;
}) {
  const [body, setBody] = useState('');

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const nextBody = body.trim();
    if (!nextBody) return;

    await onSubmit(nextBody);
    setBody('');
  };

  return (
    <form onSubmit={handleSubmit} className="mt-3 flex gap-2">
      <input
        value={body}
        onChange={(event) => setBody(event.target.value.slice(0, 500))}
        placeholder={placeholder}
        className="h-10 min-w-0 flex-1 border border-[#303030] bg-[#080808] px-3 text-sm text-white outline-none transition-colors placeholder:text-[#666666] focus:border-primary"
      />
      <button
        type="submit"
        disabled={!body.trim() || isSubmitting}
        className="inline-flex h-10 w-10 shrink-0 items-center justify-center bg-primary text-white transition-colors hover:bg-primary/90 disabled:opacity-50"
        aria-label="Send reply"
      >
        <Send size={15} />
      </button>
    </form>
  );
}

function ReplyItem({
  reply,
  canReply,
  isSubmitting,
  onReply,
}: {
  reply: DjLoungeReply;
  canReply: boolean;
  isSubmitting: boolean;
  onReply: (body: string, parentId?: string) => Promise<void>;
}) {
  const [isReplying, setIsReplying] = useState(false);

  return (
    <div className="border-l border-[#303030] pl-3">
      <div className="flex gap-3">
        <LoungeAvatar
          src={reply.avatarUrl}
          initial={reply.avatarInitial}
          alt={reply.authorName}
          className="h-8 w-8 shrink-0 text-xs"
        />
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
            <p className="text-sm font-semibold text-white">{reply.authorName}</p>
            <span className="text-xs text-[#777777]">{reply.handle}</span>
            <span className="text-xs text-[#555555]">{reply.timestamp}</span>
          </div>
          <p className="mt-1 text-sm leading-6 text-[#cccccc]">{reply.body}</p>
          {!reply.parentId && canReply && (
            <button
              type="button"
              onClick={() => setIsReplying((current) => !current)}
              className="mt-2 text-xs font-semibold uppercase tracking-widest text-[#888888] transition-colors hover:text-primary"
              style={{ fontFamily: 'var(--font-heading)' }}
            >
              Reply
            </button>
          )}
          {isReplying && (
            <ReplyComposer
              placeholder={`Reply to ${reply.authorName}...`}
              isSubmitting={isSubmitting}
              onSubmit={(body) => onReply(body, reply.id).then(() => setIsReplying(false))}
            />
          )}
          {reply.replies.length > 0 && (
            <div className="mt-3 grid gap-3">
              {reply.replies.map((childReply) => (
                <ReplyItem
                  key={childReply.id}
                  reply={childReply}
                  canReply={false}
                  isSubmitting={isSubmitting}
                  onReply={onReply}
                />
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

function PostCard({
  post,
  canReply,
  isSubmittingReply,
  onLike,
  onRepost,
  onBookmark,
  onReply,
}: {
  post: DjLoungePost;
  canReply: boolean;
  isSubmittingReply: boolean;
  onLike: (postId: string) => void;
  onRepost: (postId: string) => void;
  onBookmark: (postId: string) => void;
  onReply: (postId: string, body: string, parentId?: string) => Promise<void>;
}) {
  const [isThreadOpen, setIsThreadOpen] = useState(post.replies.length > 0);

  return (
    <article className="border-b border-[#222222] bg-[#0d0d0d] p-4 transition-colors hover:bg-[#111111] sm:p-5">
      <div className="flex gap-3">
        <LoungeAvatar
          src={post.avatarUrl}
          initial={post.avatarInitial}
          alt={post.authorName}
          className="h-11 w-11 shrink-0 text-lg"
        />
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
            <p className="font-semibold text-white">{post.authorName}</p>
            <span className="text-sm text-[#777777]">{post.handle}</span>
            <span className="text-sm text-[#555555]">{post.timestamp}</span>
            {post.isLive && (
              <span
                className="inline-flex items-center gap-1 bg-primary px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest text-white"
                style={{ fontFamily: 'var(--font-heading)' }}
              >
                <span className="h-1.5 w-1.5 rounded-full bg-white" />
                Live
              </span>
            )}
          </div>
          <p className="mt-0.5 text-xs uppercase tracking-widest text-[#777777]">{post.role}</p>
          <p className="mt-3 text-sm leading-6 text-[#dddddd] sm:text-base">{post.body}</p>

          {post.mediaTitle && (
            <div className="mt-4 border border-[#303030] bg-[#080808] p-4">
              <div className="mb-3 flex items-center gap-2 text-primary">
                <Radio size={16} />
                <span
                  className="text-xs font-bold uppercase tracking-widest"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  Attached Audio
                </span>
              </div>
              <p className="text-lg uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                {post.mediaTitle}
              </p>
              <p className="mt-1 text-xs text-[#888888]">{post.mediaMeta}</p>
              <div className="mt-4 flex h-8 items-end gap-1 opacity-70">
                {[10, 18, 13, 24, 16, 28, 19, 12, 22, 15, 27, 14, 20, 11].map((bar, index) => (
                  <span key={index} className="w-1 bg-primary" style={{ height: `${bar}px` }} />
                ))}
              </div>
            </div>
          )}

          <div className="mt-4 flex items-center justify-between gap-2 text-[#888888] sm:max-w-md">
            <button
              type="button"
              onClick={() => setIsThreadOpen((current) => !current)}
              className="inline-flex items-center gap-2 text-sm transition-colors hover:text-primary"
            >
              <MessageCircle size={17} />
              {post.comments}
            </button>
            <button
              type="button"
              onClick={() => onRepost(post.id)}
              className={`inline-flex items-center gap-2 text-sm transition-colors hover:text-[#FFB800] ${
                post.reposted ? 'text-[#FFB800]' : ''
              }`}
            >
              <Repeat2 size={17} />
              {post.reposts}
            </button>
            <button
              type="button"
              onClick={() => onLike(post.id)}
              className={`inline-flex items-center gap-2 text-sm transition-colors hover:text-primary ${
                post.liked ? 'text-primary' : ''
              }`}
            >
              <Heart size={17} className={post.liked ? 'fill-primary' : ''} />
              {post.likes}
            </button>
            <button
              type="button"
              className="inline-flex items-center gap-2 text-sm transition-colors hover:text-white"
            >
              <Share2 size={17} />
            </button>
            <button
              type="button"
              onClick={() => onBookmark(post.id)}
              className={`inline-flex items-center gap-2 text-sm transition-colors hover:text-[#FFB800] ${
                post.bookmarked ? 'text-[#FFB800]' : ''
              }`}
              aria-label={post.bookmarked ? 'Remove bookmark' : 'Bookmark post'}
            >
              <Bookmark size={17} className={post.bookmarked ? 'fill-[#FFB800]' : ''} />
            </button>
          </div>

          {isThreadOpen && (
            <div className="mt-4 border-t border-[#242424] pt-4">
              {canReply ? (
                <ReplyComposer
                  placeholder="Reply to this post..."
                  isSubmitting={isSubmittingReply}
                  onSubmit={(body) => onReply(post.id, body)}
                />
              ) : (
                <p className="text-sm text-[#888888]">
                  <Link to="/login" className="text-primary hover:text-primary/80">
                    Log in
                  </Link>{' '}
                  to reply.
                </p>
              )}

              {post.replies.length > 0 && (
                <div className="mt-4 grid gap-4">
                  {post.replies.map((reply) => (
                    <ReplyItem
                      key={reply.id}
                      reply={reply}
                      canReply={canReply}
                      isSubmitting={isSubmittingReply}
                      onReply={(body, parentId) => onReply(post.id, body, parentId)}
                    />
                  ))}
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </article>
  );
}

export default function DjLoungePage() {
  const { user } = useAuth();
  const [posts, setPosts] = useState<DjLoungePost[]>([]);
  const [stats, setStats] = useState<DjLoungeStats>({ postsToday: 0, djsOnline: 0, liveThreads: 0 });
  const [draft, setDraft] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [isPosting, setIsPosting] = useState(false);
  const [replyingPostId, setReplyingPostId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const { getSlot } = useFeaturedDjs();
  const loungeFeaturedSlot = getSlot(1);
  const remainingCharacters = 280 - draft.length;

  useEffect(() => {
    let isMounted = true;

    async function loadPosts() {
      try {
        setIsLoading(true);
        setError(null);
        const feed = await getDjLoungeFeed();
        if (isMounted) {
          setPosts(feed.posts);
          setStats(feed.stats);
        }
      } catch (loadError) {
        if (isMounted) {
          setError(loadError instanceof Error ? loadError.message : 'DJLounge could not load.');
        }
      } finally {
        if (isMounted) setIsLoading(false);
      }
    }

    void loadPosts();

    return () => {
      isMounted = false;
    };
  }, []);

  const feedStats = useMemo(
    () => [
      { label: 'Posts Today', value: stats.postsToday.toString() },
      { label: 'DJs Online', value: stats.djsOnline.toString() },
      { label: 'Live Threads', value: stats.liveThreads.toString() },
    ],
    [stats],
  );

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const body = draft.trim();
    if (!user || !body) return;

    try {
      setIsPosting(true);
      setError(null);
      const post = await createDjLoungePost(body);
      setPosts((currentPosts) => [post, ...currentPosts]);
      setStats((currentStats) => ({
        ...currentStats,
        postsToday: currentStats.postsToday + 1,
        djsOnline: Math.max(currentStats.djsOnline, 1),
      }));
      setDraft('');
    } catch (postError) {
      setError(postError instanceof Error ? postError.message : 'Your post could not be published.');
    } finally {
      setIsPosting(false);
    }
  };

  const handleLike = async (postId: string) => {
    if (!user) return;

    setPosts((currentPosts) =>
      currentPosts.map((post) =>
        post.id === postId
          ? {
              ...post,
              liked: !post.liked,
              likes: post.liked ? post.likes - 1 : post.likes + 1,
            }
          : post,
      ),
    );

    try {
      const result = await toggleDjLoungePostReaction(postId);
      setPosts((currentPosts) =>
        currentPosts.map((post) =>
          post.id === postId ? { ...post, liked: result.liked, likes: result.like_count } : post,
        ),
      );
    } catch (likeError) {
      setError(likeError instanceof Error ? likeError.message : 'That reaction did not save.');
      const nextPosts = await getDjLoungePosts();
      setPosts(nextPosts);
    }
  };

  const handleRepost = async (postId: string) => {
    if (!user) return;

    setPosts((currentPosts) =>
      currentPosts.map((post) =>
        post.id === postId
          ? {
              ...post,
              reposted: !post.reposted,
              reposts: post.reposted ? post.reposts - 1 : post.reposts + 1,
            }
          : post,
      ),
    );

    try {
      const result = await toggleDjLoungePostRepost(postId);
      setPosts((currentPosts) =>
        currentPosts.map((post) =>
          post.id === postId ? { ...post, reposted: result.reposted, reposts: result.repost_count } : post,
        ),
      );
    } catch (repostError) {
      setError(repostError instanceof Error ? repostError.message : 'That repost did not save.');
      const nextPosts = await getDjLoungePosts();
      setPosts(nextPosts);
    }
  };

  const handleBookmark = async (postId: string) => {
    if (!user) return;

    setPosts((currentPosts) =>
      currentPosts.map((post) =>
        post.id === postId
          ? {
              ...post,
              bookmarked: !post.bookmarked,
              bookmarks: post.bookmarked ? post.bookmarks - 1 : post.bookmarks + 1,
            }
          : post,
      ),
    );

    try {
      const result = await toggleDjLoungePostBookmark(postId);
      setPosts((currentPosts) =>
        currentPosts.map((post) =>
          post.id === postId
            ? { ...post, bookmarked: result.bookmarked, bookmarks: result.bookmark_count }
            : post,
        ),
      );
    } catch (bookmarkError) {
      setError(bookmarkError instanceof Error ? bookmarkError.message : 'That bookmark did not save.');
      const nextPosts = await getDjLoungePosts();
      setPosts(nextPosts);
    }
  };

  const handleReply = async (postId: string, body: string, parentId?: string) => {
    if (!user) return;

    try {
      setReplyingPostId(postId);
      setError(null);
      const result = await createDjLoungeReply(postId, body, parentId);

      setPosts((currentPosts) =>
        currentPosts.map((post) => {
          if (post.id !== postId) return post;

          if (!parentId) {
            return {
              ...post,
              comments: result.comment_count,
              replies: [...post.replies, result.reply],
            };
          }

          return {
            ...post,
            comments: result.comment_count,
            replies: post.replies.map((reply) =>
              reply.id === parentId
                ? {
                    ...reply,
                    replyCount: reply.replyCount + 1,
                    replies: [...reply.replies, result.reply],
                  }
                : reply,
            ),
          };
        }),
      );
    } catch (replyError) {
      setError(replyError instanceof Error ? replyError.message : 'That reply could not be published.');
    } finally {
      setReplyingPostId(null);
    }
  };

  return (
    <>
      <Helmet>
        <title>DJLounge | The Blend Battlegrounds</title>
        <meta name="description" content="Join DJLounge, the social wall for DJs and fans on The Blend Battlegrounds." />
      </Helmet>

      <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] text-white">
        <section className="border-b border-[#1a1a1a] px-4 py-10 lg:px-8">
          <div className="container mx-auto max-w-6xl">
            <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <p
                  className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary"
                  style={{ fontFamily: 'var(--font-heading)' }}
                >
                  Social Wall
                </p>
                <h1
                  className="uppercase leading-none text-white"
                  style={{ fontFamily: 'var(--font-heading)', fontSize: 'clamp(4rem, 11vw, 8rem)' }}
                >
                  DJLounge
                </h1>
                <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
                  A real-time wall for DJs to post clips, call out battles, ask the crowd, and build community around the music.
                </p>
              </div>
              <div className="grid grid-cols-3 border border-[#2a2a2a] bg-[#111111]">
                {feedStats.map((stat) => (
                  <div key={stat.label} className="border-r border-[#2a2a2a] p-4 last:border-r-0">
                    <p className="text-3xl text-primary" style={{ fontFamily: 'var(--font-heading)' }}>
                      {stat.value}
                    </p>
                    <p className="mt-1 text-[10px] uppercase tracking-widest text-[#777777]">{stat.label}</p>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </section>

        <section className="px-4 py-8 lg:px-8">
          <div className="container mx-auto grid max-w-6xl gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div className="overflow-hidden border border-[#2a2a2a] bg-[#0d0d0d]">
              <form onSubmit={handleSubmit} className="border-b border-[#222222] bg-[#111111] p-4 sm:p-5">
                {user ? (
                  <div className="flex gap-3">
                    <LoungeAvatar
                      src={user.avatar_url}
                      initial={user.name.charAt(0)}
                      alt={user.name}
                      className="h-11 w-11 shrink-0 text-lg"
                    />
                    <div className="min-w-0 flex-1">
                      <textarea
                        value={draft}
                        onChange={(event) => setDraft(event.target.value.slice(0, 280))}
                        placeholder="Drop a thought, mix update, battle callout, or crowd question..."
                        className="min-h-28 w-full resize-none border border-[#303030] bg-[#080808] p-4 text-sm leading-6 text-white outline-none transition-colors placeholder:text-[#666666] focus:border-primary"
                      />
                      <div className="mt-3 flex items-center justify-between gap-3">
                        <span className={`text-xs ${remainingCharacters < 30 ? 'text-primary' : 'text-[#777777]'}`}>
                          {remainingCharacters} characters left
                        </span>
                        <button
                          type="submit"
                          disabled={!draft.trim() || isPosting}
                          className="inline-flex h-10 items-center justify-center gap-2 bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 disabled:opacity-50"
                          style={{ fontFamily: 'var(--font-heading)' }}
                        >
                          <Send size={15} />
                          {isPosting ? 'Posting' : 'Post'}
                        </button>
                      </div>
                    </div>
                  </div>
                ) : (
                  <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                      <p className="text-lg font-semibold text-white">Join the conversation</p>
                      <p className="mt-1 text-sm text-[#888888]">Log in or register to post in DJLounge.</p>
                    </div>
                    <div className="flex gap-2">
                      <Link
                        to="/login"
                        className="inline-flex h-10 items-center justify-center border border-[#444444] px-4 text-xs font-bold uppercase tracking-widest text-[#dddddd] hover:border-primary hover:text-primary"
                        style={{ fontFamily: 'var(--font-heading)' }}
                      >
                        Login
                      </Link>
                      <Link
                        to="/register"
                        className="inline-flex h-10 items-center justify-center bg-primary px-4 text-xs font-bold uppercase tracking-widest text-white hover:bg-primary/90"
                        style={{ fontFamily: 'var(--font-heading)' }}
                      >
                        Register
                      </Link>
                    </div>
                  </div>
                )}
              </form>

              {error && (
                <div className="border-b border-[#222222] bg-[#160909] p-4 text-sm text-primary sm:p-5">{error}</div>
              )}

              {isLoading ? (
                <div className="p-6 text-sm text-[#888888]">Loading the lounge...</div>
              ) : posts.length > 0 ? (
                posts.map((post) => (
                  <PostCard
                    key={post.id}
                    post={post}
                    canReply={Boolean(user)}
                    isSubmittingReply={replyingPostId === post.id}
                    onLike={handleLike}
                    onRepost={handleRepost}
                    onBookmark={handleBookmark}
                    onReply={handleReply}
                  />
                ))
              ) : (
                <div className="p-6 text-sm text-[#888888]">
                  The lounge is quiet. Drop the first post and set the tone.
                </div>
              )}
            </div>

            <aside className="grid gap-5 self-start">
              {loungeFeaturedSlot && (
                <FeaturedDjSlotCard
                  slot={loungeFeaturedSlot}
                  emptyMessage="This spotlight is open for DJs who want premium visibility in DJLounge."
                />
              )}

              <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                <div className="mb-4 flex items-center gap-2">
                  <Sparkles size={18} className="text-primary" />
                  <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    Trending
                  </h2>
                </div>
                <div className="grid gap-3">
                  {djLoungeTrends.map((trend) => (
                    <div key={trend.label} className="border border-[#282828] bg-[#090909] p-3">
                      <p className="text-sm font-semibold text-white">{trend.label}</p>
                      <p className="mt-1 text-xs text-[#777777]">{trend.meta}</p>
                    </div>
                  ))}
                </div>
              </section>

              <section className="border border-[#2a2a2a] bg-[#111111] p-5">
                <div className="mb-4 flex items-center gap-2">
                  <Users size={18} className="text-[#FFB800]" />
                  <h2 className="text-2xl uppercase text-white" style={{ fontFamily: 'var(--font-heading)' }}>
                    Lounge Rules
                  </h2>
                </div>
                <div className="grid gap-2 text-sm leading-6 text-[#aaaaaa]">
                  <p>Keep feedback useful.</p>
                  <p>Credit samples, collaborators, and venues.</p>
                  <p>No spam, harassment, or fake battle claims.</p>
                </div>
              </section>
            </aside>
          </div>
        </section>
      </main>
    </>
  );
}
