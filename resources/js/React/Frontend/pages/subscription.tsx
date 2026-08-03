import { Navigate, useLocation } from 'react-router-dom';

export default function SubscriptionPage() {
  const location = useLocation();
  const plan = new URLSearchParams(location.search).get('plan');
  const destination = plan
    ? `/pricing?plan=${encodeURIComponent(plan)}#membership-plans`
    : '/pricing#membership-plans';

  return <Navigate to={destination} replace />;
}
