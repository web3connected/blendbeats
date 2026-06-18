import UniversalAdCard from '@/components/advertising/UniversalAdCard';

type GroupDisplayProps = {
  compact?: boolean;
};

export default function GroupAAndBDisplay({ compact = false }: GroupDisplayProps) {
  return <UniversalAdCard placement="group-a-and-b-display" title="Premium Spotlight" compact={compact} />;
}
