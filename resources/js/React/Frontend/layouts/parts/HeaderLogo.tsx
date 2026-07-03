import { Link } from 'react-router-dom';

import logoImage from '../../../../../assets/logo.png';

export default function HeaderLogo() {
  return (
    <Link to="/" className="flex max-w-[170px] shrink-0 items-center overflow-hidden sm:max-w-[210px] xl:max-w-[260px] 2xl:max-w-[390px]">
      <img
        src={logoImage}
        alt="The Blend Battlegrounds"
        className="h-14 w-auto max-w-full shrink-0 object-contain sm:h-16 2xl:h-20"
      />
    </Link>
  );
}
