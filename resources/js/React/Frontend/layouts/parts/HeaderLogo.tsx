import { Link } from 'react-router-dom';

import logoImage from '../../../../../assets/logo.png';

export default function HeaderLogo() {
  return (
    <Link to="/" className="flex max-w-[390px] shrink-0 items-center overflow-hidden">
      <img
        src={logoImage}
        alt="The Blend Battlegrounds"
        className="h-20 w-auto max-w-full shrink-0 object-contain"
      />
    </Link>
  );
}
