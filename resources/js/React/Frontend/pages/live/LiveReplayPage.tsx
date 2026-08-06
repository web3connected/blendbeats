import { Helmet } from '@dr.pogodin/react-helmet';
import { AlertTriangle, Eye, Heart, MessageCircle, Play, Share2 } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';

import {
  addLiveReplayComment,
  getLiveDirectory,
  getLiveReplay,
  recordLiveReplayView,
  toggleLiveReplayLike,
  type LiveReplayPayload,
  type LiveStream,
} from '@/lib/live';

export default function LiveReplayPage() {
  const { id } = useParams();
  const replayId = Number(id);
  const [replay, setReplay] = useState<LiveReplayPayload | null>(null);
  const [suggested, setSuggested] = useState<LiveStream[]>([]);
  const [comment, setComment] = useState('');
  const [message, setMessage] = useState('');
  const [playbackFailed, setPlaybackFailed] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const viewed = useRef<number | null>(null);

  useEffect(() => {
    let mounted = true;
    setReplay(null);
    setMessage('');
    setPlaybackFailed(false);

    Promise.all([getLiveReplay(replayId), getLiveDirectory()])
      .then(([payload, directory]) => {
        if (!mounted) return;
        setReplay(payload);
        setSuggested(directory.saved_streams.filter((stream) => stream.id !== replayId));
      })
      .catch((error) => mounted && setMessage(error instanceof Error ? error.message : 'Unable to load this replay.'));

    if (viewed.current !== replayId) {
      viewed.current = replayId;
      recordLiveReplayView(replayId)
        .then(({ views_count }) => mounted && setReplay((current) => current ? { ...current, stream: { ...current.stream, views_count } } : current))
        .catch(() => undefined);
    }

    return () => { mounted = false; };
  }, [replayId]);

  const source = useMemo(() => (
    replay?.stream.recording_available ? replay.stream.recording_url ?? null : null
  ), [replay]);

  async function handleLike() {
    try {
      const result = await toggleLiveReplayLike(replayId);
      setReplay((current) => current ? { ...current, liked: result.liked, stream: { ...current.stream, likes_count: result.likes_count } } : current);
      setMessage('');
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Log in to like this replay.');
    }
  }

  async function handleComment(event: FormEvent) {
    event.preventDefault();
    if (!comment.trim() || isSubmitting) return;
    setIsSubmitting(true);
    try {
      const result = await addLiveReplayComment(replayId, comment.trim());
      setReplay((current) => current ? { ...current, comments: [...current.comments, result.comment] } : current);
      setComment('');
      setMessage('');
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Log in to leave a comment.');
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleShare() {
    try {
      await navigator.clipboard.writeText(window.location.href);
      setMessage('Replay link copied.');
    } catch {
      setMessage('Copy the page address to share this replay.');
    }
  }

  if (!replay) {
    return <main className="min-h-screen bg-[#070707] px-6 py-16 text-center text-[#bdbdbd]">{message || 'Loading replay...'}</main>;
  }

  const { stream, comments } = replay;
  const djName = stream.dj?.dj_name ?? stream.dj?.name ?? 'BlendBeats DJ';

  return (
    <main className="min-h-screen bg-[#070707] text-white">
      <Helmet><title>{stream.title} - BlendBeats Live</title></Helmet>
      <section className="mx-auto grid w-full max-w-7xl gap-7 px-4 py-8 sm:px-6 lg:grid-cols-[minmax(0,1fr)_340px] lg:px-8">
        <div className="min-w-0">
          <div className="aspect-video overflow-hidden rounded-lg border border-[#282828] bg-black">
            {source && !playbackFailed ? (
              <video
                key={source}
                src={source}
                controls
                playsInline
                preload="metadata"
                onError={() => setPlaybackFailed(true)}
                className="h-full w-full object-contain"
              />
            ) : (
              <div role="alert" className="flex h-full flex-col items-center justify-center gap-3 bg-[radial-gradient(circle_at_center,#242424,#080808_65%)] px-6 text-center">
                <AlertTriangle size={44} className="text-primary" aria-hidden="true" />
                <p className="font-bold uppercase tracking-[0.18em]">
                  {playbackFailed ? 'Video could not be loaded' : 'Video is missing'}
                </p>
                <p className="max-w-md text-sm leading-6 text-[#aaa]">
                  {playbackFailed
                    ? 'The recording exists, but your browser could not play it. Please refresh the page or try another browser.'
                    : 'The recording file for this saved stream is not available.'}
                </p>
              </div>
            )}
          </div>

          <h1 className="mt-5 text-3xl sm:text-4xl">{stream.title}</h1>
          <div className="mt-4 flex flex-wrap items-center justify-between gap-4 border-b border-[#282828] pb-5">
            <div>
              <p className="font-bold">{djName}</p>
              <p className="mt-1 flex items-center gap-2 text-sm text-[#999]"><Eye size={16} /> {stream.views_count.toLocaleString()} views</p>
            </div>
            <div className="flex gap-2">
              <button type="button" onClick={handleLike} className={`inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-bold transition ${replay.liked ? 'bg-primary text-white' : 'bg-[#222] hover:bg-[#303030]'}`}>
                <Heart size={17} fill={replay.liked ? 'currentColor' : 'none'} /> {stream.likes_count}
              </button>
              <button type="button" onClick={handleShare} className="inline-flex items-center gap-2 rounded-full bg-[#222] px-4 py-2 text-sm font-bold hover:bg-[#303030]"><Share2 size={17} /> Share</button>
            </div>
          </div>

          {message ? <p className="mt-4 rounded-md border border-[#333] bg-[#151515] px-4 py-3 text-sm text-[#ddd]">{message}</p> : null}

          <section className="mt-8">
            <h2 className="flex items-center gap-2 text-2xl"><MessageCircle size={22} /> {comments.length} Comments</h2>
            <form onSubmit={handleComment} className="mt-5 flex gap-3">
              <input value={comment} onChange={(event) => setComment(event.target.value)} maxLength={1000} placeholder="Add a comment..." className="min-w-0 flex-1 border-b border-[#444] bg-transparent px-1 py-3 text-sm outline-none focus:border-primary" />
              <button disabled={!comment.trim() || isSubmitting} className="rounded-md bg-primary px-5 py-2 text-sm font-bold disabled:opacity-40">Comment</button>
            </form>
            <div className="mt-6 space-y-5">
              {comments.length === 0 ? <p className="text-sm text-[#999]">Be the first to comment on this set.</p> : comments.map((item) => (
                <article key={item.id} className="flex gap-3">
                  <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#292929] text-sm font-bold">{item.user.name.charAt(0).toUpperCase()}</div>
                  <div><p className="text-sm font-bold">{item.user.name} <span className="ml-2 font-normal text-[#777]">{new Date(item.created_at).toLocaleDateString()}</span></p><p className="mt-1 text-sm leading-6 text-[#ddd]">{item.body}</p></div>
                </article>
              ))}
            </div>
          </section>
        </div>

        <aside>
          <h2 className="mb-4 text-xl">More saved streams</h2>
          <div className="space-y-3">
            {suggested.length === 0 ? <p className="text-sm text-[#888]">No other saved streams yet.</p> : suggested.map((item) => (
              <Link key={item.id} to={`/live/replay/${item.id}`} className="group grid grid-cols-[140px_1fr] gap-3 rounded-md p-2 transition hover:bg-[#151515]">
                <div className="flex aspect-video items-center justify-center rounded bg-[#1d1d1d] text-[#aaa] group-hover:text-primary"><Play size={22} fill="currentColor" /></div>
                <div className="min-w-0"><h3 className="line-clamp-2 font-bold leading-5">{item.title}</h3><p className="mt-2 truncate text-xs text-[#999]">{item.dj?.dj_name ?? item.dj?.name ?? 'BlendBeats DJ'}</p><p className="mt-1 text-xs text-[#777]">{item.views_count ?? 0} views</p></div>
              </Link>
            ))}
          </div>
        </aside>
      </section>
    </main>
  );
}
