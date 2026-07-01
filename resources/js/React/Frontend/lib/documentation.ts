export type DocumentationArticleStatus = 'active' | 'foundation' | 'future';

export type DocumentationCategory = {
  slug: string;
  title: string;
  description: string;
};

export type DocumentationSection = {
  title: string;
  body: string;
  bullets?: string[];
};

export type DocumentationArticle = {
  slug: string;
  title: string;
  category: string;
  summary: string;
  status: DocumentationArticleStatus;
  updatedAt: string;
  manageHref?: string;
  keywords: string[];
  sections: DocumentationSection[];
  relatedSlugs: string[];
};

export const documentationCategories: DocumentationCategory[] = [
  {
    slug: 'getting-started',
    title: 'Getting Started',
    description: 'Core orientation for new users and returning members.',
  },
  {
    slug: 'account',
    title: 'Account',
    description: 'Account profile, settings, security, notifications, and storage basics.',
  },
  {
    slug: 'memberships',
    title: 'Memberships',
    description: 'Plans, billing, subscriptions, credits, and reward visibility.',
  },
  {
    slug: 'affiliate-program',
    title: 'Affiliate Program',
    description: 'Referral links, attribution, membership credits, and affiliate dashboard activity.',
  },
  {
    slug: 'dj-features',
    title: 'DJ Features',
    description: 'DJ Lounge, scratches, profiles, portfolios, uploads, mixes, and creator workflows.',
  },
  {
    slug: 'marketplace',
    title: 'Marketplace',
    description: 'Merch, featured ads, purchases, carts, downloads, and commerce foundations.',
  },
  {
    slug: 'community',
    title: 'Community',
    description: 'BlendNews, playlists, battles, badges, and community participation.',
  },
  {
    slug: 'faqs',
    title: 'FAQs',
    description: 'Common questions and future-feature notes.',
  },
];

export const documentationArticles: DocumentationArticle[] = [
  {
    slug: 'platform-overview',
    title: 'Platform Overview',
    category: 'getting-started',
    summary: 'Understand how BlendBeats connects accounts, DJ tools, memberships, community, commerce, and support.',
    status: 'active',
    updatedAt: '2026-06-23',
    manageHref: '/account',
    keywords: ['overview', 'onboarding', 'account area', 'blendbeats'],
    sections: [
      {
        title: 'Account Area',
        body: 'The account area is the signed-in home for user settings, profile data, membership status, affiliate activity, playlists, notifications, support, and documentation.',
      },
      {
        title: 'Creator Tools',
        body: 'DJ tools are grouped around public identity, portfolio media, DJ Lounge participation, scratches, mixes, battles, badges, and future marketplace discovery.',
      },
      {
        title: 'Commerce And Rewards',
        body: 'Memberships, featured ads, payment methods, merch, affiliate membership credits, and rewards are separate surfaces that share account-level visibility.',
      },
    ],
    relatedSlugs: ['first-steps', 'account-management', 'memberships-subscriptions'],
  },
  {
    slug: 'first-steps',
    title: 'First Steps',
    category: 'getting-started',
    summary: 'A short path for setting up an account, finding core pages, and deciding what to do next.',
    status: 'active',
    updatedAt: '2026-06-23',
    manageHref: '/account',
    keywords: ['start', 'setup', 'onboarding', 'new user'],
    sections: [
      {
        title: 'Set Up Your Account',
        body: 'Start with your profile, avatar, contact details, timezone, and basic account settings so the rest of the platform can show accurate account information.',
        bullets: ['Open Profile for identity and contact details.', 'Open Settings for account-level areas.', 'Open Notifications to review account messages.'],
      },
      {
        title: 'Choose Your Lane',
        body: 'Listeners can save playlists, follow community activity, and browse content. DJs can create a public DJ profile, upload media, and use creator tools.',
      },
      {
        title: 'Check Membership And Rewards',
        body: 'Your membership tier controls upload storage and platform access. Affiliate rewards currently issue free membership credits when referrals qualify.',
      },
    ],
    relatedSlugs: ['profile-management', 'dj-portfolio-and-mixes', 'affiliate-program'],
  },
  {
    slug: 'account-management',
    title: 'Account Management',
    category: 'account',
    summary: 'Review the main account surfaces for profile data, settings, storage, billing, support, and documentation.',
    status: 'active',
    updatedAt: '2026-06-23',
    manageHref: '/account/settings',
    keywords: ['settings', 'account center', 'support', 'storage'],
    sections: [
      {
        title: 'Settings Hub',
        body: 'Settings is the account hub for profile management, billing, membership, featured ads, storage, security, notifications, support, and documentation.',
      },
      {
        title: 'Account Dashboard',
        body: 'The account dashboard highlights membership status, recent achievement activity, playlist access, DJ tools, and common account actions.',
      },
      {
        title: 'Support And Documentation',
        body: 'Support is the place to collect issue details. Documentation is the self-service reference for workflows, feature explanations, and common questions.',
      },
    ],
    relatedSlugs: ['profile-management', 'notifications', 'platform-overview'],
  },
  {
    slug: 'profile-management',
    title: 'Profile Management',
    category: 'account',
    summary: 'Manage personal account details, avatar, social links, public identity, and DJ profile connections.',
    status: 'active',
    updatedAt: '2026-06-23',
    manageHref: '/account/profile',
    keywords: ['profile', 'avatar', 'social links', 'dj profile'],
    sections: [
      {
        title: 'Account Profile',
        body: 'Your account profile stores the personal account data used across BlendBeats, including name, email, contact details, location, timezone, avatar, and bio.',
      },
      {
        title: 'Public DJ Identity',
        body: 'A DJ profile is separate from the private account profile. It controls public DJ name, handle, genres, booking details, portfolio connections, and public discovery.',
      },
      {
        title: 'Avatar Source',
        body: 'BlendBeats uses one shared avatar source for account pages, public profiles, posts, and community activity.',
      },
    ],
    relatedSlugs: ['account-management', 'dj-portfolio-and-mixes', 'dj-lounge'],
  },
  {
    slug: 'notifications',
    title: 'Notifications',
    category: 'account',
    summary: 'Review account notifications for platform events, affiliate activity, rewards, billing, and future reminders.',
    status: 'active',
    updatedAt: '2026-06-23',
    manageHref: '/account/notifications',
    keywords: ['notifications', 'alerts', 'messages', 'affiliate notifications'],
    sections: [
      {
        title: 'Notification Center',
        body: 'The notification center gathers user-facing account messages so important events can be reviewed from one signed-in location.',
      },
      {
        title: 'Affiliate Events',
        body: 'Affiliate notifications cover account creation, referred signups, qualification, membership credit issuance, redemption, expiration warnings, and expired credits.',
      },
      {
        title: 'Future Preferences',
        body: 'Notification preferences can grow into email, in-app, and digest settings without changing the core notification record flow.',
      },
    ],
    relatedSlugs: ['affiliate-program', 'credits-rewards', 'account-management'],
  },
  {
    slug: 'memberships-subscriptions',
    title: 'Memberships And Subscriptions',
    category: 'memberships',
    summary: 'Understand membership tiers, subscription status, payment methods, storage, billing, and renewal visibility.',
    status: 'active',
    updatedAt: '2026-06-23',
    manageHref: '/account/billing',
    keywords: ['membership', 'subscription', 'billing', 'payment methods', 'plans'],
    sections: [
      {
        title: 'Membership Status',
        body: 'The account dashboard and billing pages show the current plan, status, billing provider, subscription identifier, approval date, expiration date, and plan reason when available.',
      },
      {
        title: 'Storage And Access',
        body: 'Membership tiers control storage allowance and can unlock promotional access levels. Free accounts keep core access and starter storage.',
      },
      {
        title: 'Payment Methods',
        body: 'Payment methods are managed separately from the subscription summary so providers can be enabled, disabled, or expanded over time.',
      },
    ],
    relatedSlugs: ['credits-rewards', 'purchases-downloads', 'account-management'],
  },
  {
    slug: 'credits-rewards',
    title: 'Credits And Rewards',
    category: 'memberships',
    summary: 'Track how rewards, badges, affiliate membership credits, and credit expiration fit together.',
    status: 'foundation',
    updatedAt: '2026-06-23',
    manageHref: '/account/badges',
    keywords: ['credits', 'rewards', 'badges', 'membership credits', 'affiliate rewards'],
    sections: [
      {
        title: 'Affiliate Membership Credits',
        body: 'The active affiliate reward is a free membership credit. A successful referred subscription payment issues a credit that can stack with other earned credits.',
        bullets: ['Credits expire 12 months after issue.', 'Expired unused credits cannot be redeemed.', 'There is no earning cap for qualified referrals.'],
      },
      {
        title: 'Badges And Achievements',
        body: 'The badges area displays unlocked achievements, locked badges, rarity, and progress events tied to account activity.',
      },
      {
        title: 'Reward Visibility',
        body: 'Reward records support status tracking and audit history so dashboard and admin views can explain what happened to each credit.',
      },
    ],
    relatedSlugs: ['affiliate-program', 'memberships-subscriptions', 'notifications'],
  },
  {
    slug: 'affiliate-program',
    title: 'Affiliate Program',
    category: 'affiliate-program',
    summary: 'Use referral links and codes to invite users and earn membership credits when referrals qualify.',
    status: 'active',
    updatedAt: '2026-06-23',
    manageHref: '/account/affiliate',
    keywords: ['affiliate', 'referral', 'referral links', 'membership credit', 'campaigns'],
    sections: [
      {
        title: 'Affiliate Account',
        body: 'A user can join the BlendBeats Affiliate Program from the account area. Joining creates an affiliate account, assigns affiliate status, and establishes an affiliate profile.',
      },
      {
        title: 'Referral Links And Attribution',
        body: 'Each affiliate receives a referral code and link. Referral visits are captured, stored for signup attribution, and connected to the new user when registration occurs.',
      },
      {
        title: 'Qualification And Rewards',
        body: 'A referral qualifies when the referred user successfully purchases a subscription. The active reward is a free membership credit, while payout features remain built but disabled.',
      },
      {
        title: 'Campaigns And Analytics',
        body: 'Referral campaigns group codes and analytics for seasonal pushes, influencer links, and targeted programs. Admin analytics track visits, signups, qualifications, and top affiliates.',
      },
    ],
    relatedSlugs: ['credits-rewards', 'notifications', 'memberships-subscriptions'],
  },
  {
    slug: 'dj-lounge',
    title: 'DJ Lounge',
    category: 'dj-features',
    summary: 'Post, react, and keep up with community activity in the signed-in DJ Lounge experience.',
    status: 'active',
    updatedAt: '2026-06-23',
    manageHref: '/dj-lounge',
    keywords: ['dj lounge', 'community wall', 'posts', 'reactions'],
    sections: [
      {
        title: 'Community Activity',
        body: 'DJ Lounge is the community wall for signed-in users to follow platform activity, post updates, react, and stay connected to DJ culture.',
      },
      {
        title: 'Account Identity',
        body: 'Lounge activity uses your account identity and avatar. DJs with public profiles can connect community activity back to creator identity.',
      },
    ],
    relatedSlugs: ['profile-management', 'dj-battle-system', 'playlists'],
  },
  {
    slug: 'dj-scratches',
    title: 'DJ Scratches',
    category: 'dj-features',
    summary: 'Explore the DJ scratches area and its role in the broader DJ feature set.',
    status: 'active',
    updatedAt: '2026-06-23',
    manageHref: '/djs/scratches',
    keywords: ['scratches', 'dj scratches', 'dj tools'],
    sections: [
      {
        title: 'Scratch Area',
        body: 'DJ Scratches gives the DJ section a dedicated surface for scratch-focused content and future creative workflows.',
      },
      {
        title: 'DJ Feature Family',
        body: 'Scratches sits beside public DJ profiles, portfolio uploads, mixes, DJ Lounge, battles, and badges as part of the creator feature set.',
      },
    ],
    relatedSlugs: ['dj-battle-system', 'dj-portfolio-and-mixes', 'dj-lounge'],
  },
  {
    slug: 'dj-portfolio-and-mixes',
    title: 'DJ Portfolio And Mixes',
    category: 'dj-features',
    summary: 'Upload and manage mixes, tracks, video, cover art, visibility, storage, and public portfolio media.',
    status: 'active',
    updatedAt: '2026-06-23',
    manageHref: '/dj/portfolio',
    keywords: ['portfolio', 'mixes', 'uploads', 'storage', 'media manager'],
    sections: [
      {
        title: 'Portfolio Uploads',
        body: 'The DJ Portfolio is where DJs upload audio, video, images, cover art, and creator media with title, genre, description, media type, and visibility.',
      },
      {
        title: 'Public Mixes',
        body: 'Media appears publicly only when visibility is public and the media URL is available through the media manager.',
      },
      {
        title: 'Storage Limits',
        body: 'Uploaded creator media counts toward account storage, and membership tier controls total available storage.',
      },
    ],
    relatedSlugs: ['profile-management', 'memberships-subscriptions', 'dj-scratches'],
  },
  {
    slug: 'beat-marketplace',
    title: 'Beat Marketplace',
    category: 'marketplace',
    summary: 'Follow the commerce foundation for products, partner items, future beat listings, and marketplace expansion.',
    status: 'foundation',
    updatedAt: '2026-06-23',
    manageHref: '/merch',
    keywords: ['marketplace', 'beats', 'merch', 'commerce', 'products'],
    sections: [
      {
        title: 'Commerce Foundation',
        body: 'BlendBeats currently has a merch and product commerce foundation that supports internal products, affiliate redirects, vendor checkout, and marketplace partner items.',
      },
      {
        title: 'Beat Marketplace Direction',
        body: 'The beat marketplace can build on the same product, cart, partner checkout, and account purchase visibility patterns as the commerce area grows.',
      },
    ],
    relatedSlugs: ['purchases-downloads', 'featured-ads-promotions', 'platform-overview'],
  },
  {
    slug: 'featured-ads-promotions',
    title: 'Featured Ads And Promotions',
    category: 'marketplace',
    summary: 'Manage promotional placements, campaign groups, slots, checkout readiness, impressions, clicks, and analytics.',
    status: 'active',
    updatedAt: '2026-06-23',
    manageHref: '/account/featured-ads',
    keywords: ['featured ads', 'promotions', 'campaigns', 'placements', 'analytics'],
    sections: [
      {
        title: 'Placement Groups',
        body: 'Featured ad groups represent visibility levels and placement opportunities. Campaign setup connects a selected placement to payment and availability.',
      },
      {
        title: 'Analytics',
        body: 'Featured Ad Analytics tracks impressions, clicks, CTR, and campaign performance so users can review promotional impact.',
      },
      {
        title: 'Payment Readiness',
        body: 'Campaign purchases require an active configured payment method before users can claim paid placement.',
      },
    ],
    relatedSlugs: ['purchases-downloads', 'memberships-subscriptions', 'beat-marketplace'],
  },
  {
    slug: 'purchases-downloads',
    title: 'Purchases And Downloads',
    category: 'marketplace',
    summary: 'Understand cart behavior, payment methods, product fulfillment, purchase visibility, and download foundations.',
    status: 'foundation',
    updatedAt: '2026-06-23',
    manageHref: '/account/payment-methods',
    keywords: ['purchases', 'downloads', 'cart', 'checkout', 'payment methods'],
    sections: [
      {
        title: 'Cart And Checkout',
        body: 'The commerce cart can route items between BlendBeats checkout, partner redirects, vendor checkout, and print-on-demand fulfillment.',
      },
      {
        title: 'Payment Methods',
        body: 'Payment provider configuration controls whether purchases, subscriptions, and promotional campaigns can complete checkout.',
      },
      {
        title: 'Download Foundation',
        body: 'Download handling exists in the media layer and can become a user-facing download history as product and media purchase workflows mature.',
      },
    ],
    relatedSlugs: ['beat-marketplace', 'memberships-subscriptions', 'featured-ads-promotions'],
  },
  {
    slug: 'blendnews',
    title: 'BlendNews',
    category: 'community',
    summary: 'Read published DJ culture news, categories, sources, and editorial stories.',
    status: 'active',
    updatedAt: '2026-06-23',
    manageHref: '/news',
    keywords: ['news', 'blendnews', 'community', 'articles'],
    sections: [
      {
        title: 'News Index',
        body: 'BlendNews publishes stories through server-rendered news routes with category pages, story detail pages, sources, and editorial metadata.',
      },
      {
        title: 'Editorial Workflow',
        body: 'Admins manage BlendNews posts, categories, sources, tags, events, and automation-assisted draft intake through the admin area.',
      },
    ],
    relatedSlugs: ['platform-overview', 'dj-battle-system', 'playlists'],
  },
  {
    slug: 'playlists',
    title: 'Playlists',
    category: 'community',
    summary: 'Save favorite mixes and keep a personal BlendBeats queue in the account area.',
    status: 'active',
    updatedAt: '2026-06-23',
    manageHref: '/account/playlist',
    keywords: ['playlist', 'favorites', 'mixes', 'queue'],
    sections: [
      {
        title: 'Personal Queue',
        body: 'My Playlist stores saved mixes and helps users keep a personal BlendBeats listening queue.',
      },
      {
        title: 'Mix Discovery',
        body: 'Playlists connect listener behavior back to public mixes, DJ profiles, and future recommendation features.',
      },
    ],
    relatedSlugs: ['dj-battle-system', 'dj-portfolio-and-mixes', 'dj-lounge'],
  },
  {
    slug: 'dj-battle-system',
    title: 'DJ Battle System',
    category: 'community',
    summary: 'Understand DJ discovery, challenge invitations, ready checks, wallet stakes, battle recording, fan voting, rewards, and leaderboards.',
    status: 'active',
    updatedAt: '2026-06-29',
    manageHref: '/battles',
    keywords: [
      'battles',
      'dj battles',
      'challenge',
      'battle ready',
      'wallet',
      'stakes',
      'recording',
      'fan voting',
      'leaderboards',
      'rewards',
    ],
    sections: [
      {
        title: 'Battle Hub',
        body: 'The DJ Battlegrounds page is the discovery hub for finding battle-ready DJs. It shows public DJ cards with names, handles, availability, basic battle stats, badges, and a Battle Ready action that opens the challenge flow.',
        bullets: [
          'Use search and filters to find DJs by genre, country, skill level, verification, and availability.',
          'Use Vote On Battles to find battles currently open for fan voting.',
          'Use Leaderboards to compare DJs by overall battle score or by individual judging category.',
        ],
      },
      {
        title: 'Create Challenge',
        body: 'Starting a battle creates a challenge invitation instead of starting the battle immediately. The challenger selects a battle length, token stake, standard rules, and an optional message. The invited DJ has 24 hours to accept or decline.',
        bullets: [
          'Pending challenges can pause if the invited DJ does not respond in time.',
          'The challenger can return to active battles from the Battle Hub banner.',
          'A DJ can accept a challenge before having enough tokens, but cannot become ready until requirements are met.',
        ],
      },
      {
        title: 'Ready Check And Wallet Stakes',
        body: 'After acceptance, both DJs enter the ready phase. Each DJ must press I am Ready before the battle starts. The ready checklist explains missing requirements so a disabled button is understandable.',
        bullets: [
          'Each DJ must have enough available BlendBeat Tokens for the selected stake.',
          'Each DJ must be public, active, battle-ready, and not already in another active battle.',
          'When both DJs are ready, both stakes are locked in their wallets, 10 percent of the total pot becomes the fan reward pool, and the rest becomes the winner prize pool.',
        ],
      },
      {
        title: 'Recording Phase',
        body: 'Once both DJs are ready, the battle moves into recording. The recording page checks camera and microphone access, supports a short countdown before capture, and allows limited re-record attempts for testing and performance correction.',
        bullets: [
          'The current recording window is 24 hours after the battle starts.',
          'Each DJ submits their own entry independently.',
          'A DJ cannot view the other submission during recording.',
          'Testing tools can duplicate the first entry into the missing slot while the AI sample pack workflow is still bypassed.',
        ],
      },
      {
        title: 'Fan Voting',
        body: 'When both recordings are submitted, the battle enters fan voting. Fans watch one DJ performance at a time, score that DJ immediately, then repeat the flow for the second DJ before reviewing and submitting a locked vote.',
        bullets: [
          'Competing DJs cannot vote in their own battle.',
          'Each fan can submit one completed vote per battle.',
          'The scorecard has ten required categories, each scored from 1 to 10.',
          'Submitted fans become eligible for the fan reward pool when the battle concludes.',
        ],
      },
      {
        title: 'Scorecards And Leaderboards',
        body: 'Battle scorecards power both battle results and the DJ Battle Leaderboards. Overall rankings use average total score, while category rankings use the matching scorecard category such as scratching ability, mixing ability, creativity, or sample integration.',
        bullets: [
          'Only completed battles with submitted scorecards count toward official leaderboard data.',
          'DJs below the minimum completed battle requirement can appear as new competitors.',
          'Leaderboards can filter by period, active DJs, and verified DJs as the competitive system grows.',
        ],
      },
      {
        title: 'Completion And Rewards',
        body: 'When the voting window ends, the system calculates average fan scores, determines the winner or draw, settles locked stakes, credits simulated winner rewards, and distributes eligible fan rewards from the fan reward pool.',
        bullets: [
          'Draws unlock stakes instead of paying a winner prize.',
          'Winner and fan reward payouts use the wallet economy simulation settings while the beta token economy is active.',
          'Completed battle results remain available for battle history and leaderboard calculations.',
        ],
      },
    ],
    relatedSlugs: ['badges-and-battles', 'credits-rewards', 'dj-portfolio-and-mixes'],
  },
  {
    slug: 'badges-and-battles',
    title: 'Badges And Battles',
    category: 'community',
    summary: 'Track achievements, battle participation, voting, progress events, and community status.',
    status: 'active',
    updatedAt: '2026-06-23',
    manageHref: '/account/badges',
    keywords: ['badges', 'battles', 'achievements', 'votes', 'progress'],
    sections: [
      {
        title: 'Badges',
        body: 'Badges show achievement progress, unlocked status, rarity, and recent account activity events.',
      },
      {
        title: 'Battles',
        body: 'Battles let users explore DJ matchups, create challenges, complete ready checks, record entries, vote on live battles, and follow the DJs gaining attention.',
      },
    ],
    relatedSlugs: ['dj-battle-system', 'dj-lounge', 'playlists'],
  },
  {
    slug: 'common-questions',
    title: 'Common Questions',
    category: 'faqs',
    summary: 'Answers to frequent account, upload, billing, affiliate, and feature availability questions.',
    status: 'active',
    updatedAt: '2026-06-23',
    manageHref: '/account/support',
    keywords: ['faq', 'questions', 'help', 'support'],
    sections: [
      {
        title: 'Where Do I Upload Mixes?',
        body: 'Use DJ Portfolio to upload media, add cover art, set visibility, and manage public mixes.',
      },
      {
        title: 'Where Do I Manage Payment Methods?',
        body: 'Use the account payment methods page. Billing keeps subscription status and provider information visible.',
      },
      {
        title: 'How Do Affiliate Rewards Work?',
        body: 'Qualified subscription referrals earn free membership credits. Payout tools are built for future use but disabled in the active affiliate program.',
      },
    ],
    relatedSlugs: ['dj-portfolio-and-mixes', 'memberships-subscriptions', 'affiliate-program'],
  },
  {
    slug: 'future-features',
    title: 'Future Features',
    category: 'faqs',
    summary: 'A reference point for future tutorials, videos, screenshots, editor tools, and expanded article management.',
    status: 'future',
    updatedAt: '2026-06-23',
    manageHref: '/account/docs',
    keywords: ['future', 'tutorials', 'videos', 'screenshots', 'editor'],
    sections: [
      {
        title: 'Documentation Growth',
        body: 'The current Documentation Center establishes categories, routes, article structure, search, account navigation, and admin visibility.',
      },
      {
        title: 'Future Editing',
        body: 'Article management can later add markdown, screenshots, video embeds, draft review, publishing status, and an editor without redesigning the user-facing routes.',
      },
    ],
    relatedSlugs: ['platform-overview', 'common-questions', 'account-management'],
  },
];

export const documentationCategoryMap = new Map(documentationCategories.map((category) => [category.slug, category]));
export const documentationArticleMap = new Map(documentationArticles.map((article) => [article.slug, article]));

export function getDocumentationCategory(slug: string): DocumentationCategory | undefined {
  return documentationCategoryMap.get(slug);
}

export function getDocumentationArticle(slug: string): DocumentationArticle | undefined {
  return documentationArticleMap.get(slug);
}

export function getArticlesForCategory(categorySlug: string): DocumentationArticle[] {
  return documentationArticles.filter((article) => article.category === categorySlug);
}

export function getRelatedDocumentationArticles(article: DocumentationArticle): DocumentationArticle[] {
  return article.relatedSlugs
    .map((slug) => documentationArticleMap.get(slug))
    .filter((related): related is DocumentationArticle => Boolean(related));
}

export function documentationStatusLabel(status: DocumentationArticleStatus): string {
  if (status === 'active') return 'Active';
  if (status === 'foundation') return 'Foundation';
  return 'Future';
}

export function searchDocumentation(query: string): DocumentationArticle[] {
  const normalizedQuery = query.trim().toLowerCase();

  if (!normalizedQuery) return documentationArticles;

  return documentationArticles.filter((article) => {
    const category = documentationCategoryMap.get(article.category);
    const searchableText = [
      article.title,
      article.summary,
      article.status,
      category?.title ?? '',
      category?.description ?? '',
      ...article.keywords,
      ...article.sections.flatMap((section) => [
        section.title,
        section.body,
        ...(section.bullets ?? []),
      ]),
    ].join(' ').toLowerCase();

    return searchableText.includes(normalizedQuery);
  });
}

export const documentationStats = {
  categories: documentationCategories.length,
  articles: documentationArticles.length,
  active: documentationArticles.filter((article) => article.status === 'active').length,
  foundation: documentationArticles.filter((article) => article.status === 'foundation').length,
  future: documentationArticles.filter((article) => article.status === 'future').length,
};
