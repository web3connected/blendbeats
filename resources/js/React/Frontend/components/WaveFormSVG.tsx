const WaveFormSVG = ({ bars }: { bars: number[] }) => {
  return (
    <div className="flex items-end gap-px h-8 opacity-30">
      {bars.map((h, i) => (
        <div
          key={i}
          className="w-1 bg-primary rounded-sm"
          style={{ height: `${h * 2}px` }}
        />
      ))}
    </div>
  );
};

export default WaveFormSVG;
