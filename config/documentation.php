<?php

return [
    'source' => 'resources/js/React/Frontend/lib/documentation.ts',

    'categories' => [
        ['slug' => 'getting-started', 'title' => 'Getting Started', 'description' => 'Core orientation for new users and returning members.'],
        ['slug' => 'account', 'title' => 'Account', 'description' => 'Account profile, settings, security, notifications, and storage basics.'],
        ['slug' => 'memberships', 'title' => 'Memberships', 'description' => 'Plans, billing, subscriptions, credits, and reward visibility.'],
        ['slug' => 'affiliate-program', 'title' => 'Affiliate Program', 'description' => 'Referral links, attribution, membership credits, and affiliate dashboard activity.'],
        ['slug' => 'dj-features', 'title' => 'DJ Features', 'description' => 'DJ Lounge, scratches, profiles, portfolios, uploads, mixes, and creator workflows.'],
        ['slug' => 'marketplace', 'title' => 'Marketplace', 'description' => 'Merch, featured ads, purchases, carts, downloads, and commerce foundations.'],
        ['slug' => 'community', 'title' => 'Community', 'description' => 'BlendNews, playlists, battles, badges, and community participation.'],
        ['slug' => 'faqs', 'title' => 'FAQs', 'description' => 'Common questions and future-feature notes.'],
    ],

    'articles' => [
        ['slug' => 'platform-overview', 'title' => 'Platform Overview', 'category' => 'getting-started', 'status' => 'active', 'route' => '/account/docs/platform-overview'],
        ['slug' => 'first-steps', 'title' => 'First Steps', 'category' => 'getting-started', 'status' => 'active', 'route' => '/account/docs/first-steps'],
        ['slug' => 'account-management', 'title' => 'Account Management', 'category' => 'account', 'status' => 'active', 'route' => '/account/docs/account-management'],
        ['slug' => 'profile-management', 'title' => 'Profile Management', 'category' => 'account', 'status' => 'active', 'route' => '/account/docs/profile-management'],
        ['slug' => 'notifications', 'title' => 'Notifications', 'category' => 'account', 'status' => 'active', 'route' => '/account/docs/notifications'],
        ['slug' => 'memberships-subscriptions', 'title' => 'Memberships And Subscriptions', 'category' => 'memberships', 'status' => 'active', 'route' => '/account/docs/memberships-subscriptions'],
        ['slug' => 'credits-rewards', 'title' => 'Credits And Rewards', 'category' => 'memberships', 'status' => 'foundation', 'route' => '/account/docs/credits-rewards'],
        ['slug' => 'affiliate-program', 'title' => 'Affiliate Program', 'category' => 'affiliate-program', 'status' => 'active', 'route' => '/account/docs/affiliate-program'],
        ['slug' => 'dj-lounge', 'title' => 'DJ Lounge', 'category' => 'dj-features', 'status' => 'active', 'route' => '/account/docs/dj-lounge'],
        ['slug' => 'dj-scratches', 'title' => 'DJ Scratches', 'category' => 'dj-features', 'status' => 'active', 'route' => '/account/docs/dj-scratches'],
        ['slug' => 'dj-portfolio-and-mixes', 'title' => 'DJ Portfolio And Mixes', 'category' => 'dj-features', 'status' => 'active', 'route' => '/account/docs/dj-portfolio-and-mixes'],
        ['slug' => 'beat-marketplace', 'title' => 'Beat Marketplace', 'category' => 'marketplace', 'status' => 'foundation', 'route' => '/account/docs/beat-marketplace'],
        ['slug' => 'featured-ads-promotions', 'title' => 'Featured Ads And Promotions', 'category' => 'marketplace', 'status' => 'active', 'route' => '/account/docs/featured-ads-promotions'],
        ['slug' => 'purchases-downloads', 'title' => 'Purchases And Downloads', 'category' => 'marketplace', 'status' => 'foundation', 'route' => '/account/docs/purchases-downloads'],
        ['slug' => 'blendnews', 'title' => 'BlendNews', 'category' => 'community', 'status' => 'active', 'route' => '/account/docs/blendnews'],
        ['slug' => 'playlists', 'title' => 'Playlists', 'category' => 'community', 'status' => 'active', 'route' => '/account/docs/playlists'],
        ['slug' => 'badges-and-battles', 'title' => 'Badges And Battles', 'category' => 'community', 'status' => 'active', 'route' => '/account/docs/badges-and-battles'],
        ['slug' => 'common-questions', 'title' => 'Common Questions', 'category' => 'faqs', 'status' => 'active', 'route' => '/account/docs/common-questions'],
        ['slug' => 'future-features', 'title' => 'Future Features', 'category' => 'faqs', 'status' => 'future', 'route' => '/account/docs/future-features'],
    ],
];
