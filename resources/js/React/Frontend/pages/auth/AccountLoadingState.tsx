type AccountLoadingStateProps = {
  maxWidthClassName?: string;
};

export default function AccountLoadingState({ maxWidthClassName = 'max-w-6xl' }: AccountLoadingStateProps) {
  return (
    <main className="min-h-[calc(100vh-5rem)] bg-[#0a0a0a] px-4 py-20">
      <div className={`container mx-auto ${maxWidthClassName}`}>
        <div className="h-48 animate-pulse bg-[#141414]" />
      </div>
    </main>
  );
}
