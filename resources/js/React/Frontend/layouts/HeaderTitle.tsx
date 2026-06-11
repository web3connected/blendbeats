import { Helmet } from '@dr.pogodin/react-helmet';

const HeaderTitle = ({
    title,
    description
}: {
    title: string
    description: string
}) => {
  return (
    <Helmet>
        <title>{title}</title>
        <meta name="description" content={description} />
      </Helmet>
  )
}

export default HeaderTitle