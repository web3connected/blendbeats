import UniversalAdCard from '@/components/advertising/UniversalAdCard';

type GroupDisplayProps = {
  compact?: boolean;
};

export default function GroupEAndFDisplay({ compact = false }: GroupDisplayProps) {
  return <UniversalAdCard placement="group-e-and-f-display" title="Community Spotlight" compact={compact} />;
}
