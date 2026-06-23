import { Navigate, useParams } from 'react-router-dom';

const legacyDocRoutes: Record<string, string> = {
  'account-profile': 'account-management',
  'uploads-storage': 'dj-portfolio-and-mixes',
  'billing-payments': 'memberships-subscriptions',
  'featured-ads': 'featured-ads-promotions',
  'upload-mixes': 'dj-portfolio-and-mixes',
  'payment-methods': 'purchases-downloads',
  'ad-performance': 'featured-ads-promotions',
};

export default function SupportDocPage() {
  const { topic = '' } = useParams();
  const slug = legacyDocRoutes[topic];

  return <Navigate to={slug ? `/account/docs/${slug}` : '/account/docs'} replace />;
}
