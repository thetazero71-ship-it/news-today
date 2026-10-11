-- ==============================================================
--  مخطط قاعدة البيانات الكامل — نسخة حديثة
--  منصة الأخبار العالمية (news-today)
--  مولَّد من قاعدة البيانات الحيّة مباشرةً بتاريخ 2026-10-11
--
--  ✅ يحتوي على أوامر الإنشاء فقط (CREATE TABLE)
--  ✅ لا يحتوي على أي جمل تعديل على جداول قائمة
--  ✅ لا يحتوي على أي بيانات (INSERT) — المخطط فقط
--
--  عدد الجداول: 55
--  عدد Views : 0
--
--  طريقة الاستيراد:
--    mysql -u USER -p DBNAME < schema.sql
--
--  ملاحظة: بعض المضيفين تمنع إنشاء الجداول بصلاحيات محدودة؛
--  استورد الملف على سيرفرك (localhost) ثم صدّره.
-- ==============================================================


--  فهرس الجداول (55):
--    roles
--    users
--    activity_logs
--    ads
--    ai_ask_throttle
--    ai_conversations
--    analytics_daily
--    api_keys
--    categories
--    series
--    articles
--    article_analytics
--    article_revisions
--    tags
--    article_tags
--    bookmark_collections
--    bookmarks
--    bot_traffic_logs
--    cache_settings
--    comments
--    contact_messages
--    cron_logs
--    live_blogs
--    live_blog_chat
--    live_blog_entries
--    live_blog_reactions
--    media
--    media_folders
--    menus
--    menu_items
--    newsletters
--    newsletter_campaigns
--    notifications
--    pages
--    password_resets
--    plans
--    polls
--    poll_options
--    poll_votes
--    push_subscriptions
--    reactions
--    reading_history
--    redirects
--    reports
--    rss_sources
--    scheduled_tasks
--    security_alerts
--    seo_meta
--    settings
--    social_accounts
--    subscriptions
--    translation_logs
--    tutorials
--    tutorial_steps
--    user_sessions

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------
-- جدول: roles
-- ---------------------------------------------------------
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `name_ar` varchar(100) DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: users
-- ---------------------------------------------------------
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(10) unsigned DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `bio_ar` text DEFAULT NULL,
  `bio_en` text DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `social_links` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`social_links`)),
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `login_count` int(10) unsigned NOT NULL DEFAULT 0,
  `preferred_language` varchar(5) NOT NULL DEFAULT 'ar',
  `theme_preference` varchar(10) NOT NULL DEFAULT 'auto',
  `ai_quota_date` date DEFAULT NULL COMMENT 'last assistant-quota date (site tz)',
  `ai_quota_used` int(11) NOT NULL DEFAULT 0 COMMENT 'assistant questions used today',
  `ai_quota_boost` int(11) NOT NULL DEFAULT 0 COMMENT 'one-time extra assistant messages granted by admin',
  `ai_quota_daily` int(11) DEFAULT NULL COMMENT 'per-user daily allowance override (NULL = global, 0 = unlimited)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role_id` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: activity_logs
-- ---------------------------------------------------------
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `model_type` varchar(80) DEFAULT NULL,
  `model_id` bigint(20) unsigned DEFAULT NULL,
  `entity_type` varchar(80) DEFAULT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activity_user` (`user_id`),
  KEY `idx_activity_created` (`created_at`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=516 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: ads
-- ---------------------------------------------------------
CREATE TABLE `ads` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(190) NOT NULL,
  `placement` varchar(50) NOT NULL,
  `ad_type` varchar(30) NOT NULL DEFAULT 'html',
  `image_path` varchar(500) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `target_url` varchar(500) DEFAULT NULL,
  `code` text DEFAULT NULL,
  `html_code` text DEFAULT NULL,
  `impressions` int(10) unsigned NOT NULL DEFAULT 0,
  `impressions_count` int(10) unsigned NOT NULL DEFAULT 0,
  `clicks` int(10) unsigned NOT NULL DEFAULT 0,
  `clicks_count` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `priority` int(11) NOT NULL DEFAULT 0,
  `target_categories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_categories`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ads_placement_status` (`placement`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: ai_ask_throttle
-- ---------------------------------------------------------
CREATE TABLE `ai_ask_throttle` (
  `ip` varchar(45) NOT NULL,
  `last_ts` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`ip`),
  KEY `last_ts` (`last_ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: ai_conversations
-- ---------------------------------------------------------
CREATE TABLE `ai_conversations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `question` text NOT NULL,
  `answer` text DEFAULT NULL,
  `provider` varchar(60) DEFAULT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'ok' COMMENT 'ok | error',
  `error` text DEFAULT NULL,
  `sources` text DEFAULT NULL COMMENT 'json: [{title,url}]',
  `page_slug` varchar(255) DEFAULT NULL,
  `reaction` varchar(10) DEFAULT NULL COMMENT 'like | dislike | love | NULL',
  `reaction_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_conversations_user` (`user_id`),
  KEY `idx_conversations_status` (`status`),
  KEY `idx_conversations_created` (`created_at`),
  CONSTRAINT `fk_conversations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI assistant conversation log';

-- ---------------------------------------------------------
-- جدول: analytics_daily
-- ---------------------------------------------------------
CREATE TABLE `analytics_daily` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `page_views` int(10) unsigned NOT NULL DEFAULT 0,
  `unique_visitors` int(10) unsigned NOT NULL DEFAULT 0,
  `new_users` int(10) unsigned NOT NULL DEFAULT 0,
  `top_articles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`top_articles`)),
  `top_categories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`top_categories`)),
  `top_referrers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`top_referrers`)),
  `devices` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`devices`)),
  `countries` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`countries`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_analytics_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: api_keys
-- ---------------------------------------------------------
CREATE TABLE `api_keys` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(190) NOT NULL,
  `key_prefix` varchar(32) NOT NULL,
  `secret_token` varchar(255) NOT NULL,
  `key_hash` varchar(255) NOT NULL,
  `scopes` text NOT NULL,
  `rate_limit_per_minute` int(10) unsigned DEFAULT 60,
  `requests_count` bigint(20) unsigned DEFAULT 0,
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `key_prefix` (`key_prefix`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: categories
-- ---------------------------------------------------------
CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `name_ar` varchar(150) DEFAULT NULL,
  `name_en` varchar(150) DEFAULT NULL,
  `slug` varchar(180) NOT NULL,
  `description_ar` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `color` varchar(30) DEFAULT NULL,
  `featured_image` varchar(1024) DEFAULT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `meta_title_ar` varchar(255) DEFAULT NULL,
  `meta_title_en` varchar(255) DEFAULT NULL,
  `meta_description_ar` text DEFAULT NULL,
  `meta_description_en` text DEFAULT NULL,
  `articles_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `idx_categories_parent_id` (`parent_id`),
  CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: series
-- ---------------------------------------------------------
CREATE TABLE `series` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title_ar` varchar(255) NOT NULL,
  `title_en` varchar(255) DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `description_ar` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'published',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_series_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: articles
-- ---------------------------------------------------------
CREATE TABLE `articles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `title_ar` varchar(255) DEFAULT NULL,
  `title_en` varchar(255) DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `content_ar` longtext DEFAULT NULL,
  `content_en` longtext DEFAULT NULL,
  `excerpt` text DEFAULT NULL,
  `excerpt_ar` text DEFAULT NULL,
  `excerpt_en` text DEFAULT NULL,
  `featured_image` varchar(1024) DEFAULT NULL,
  `gallery` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gallery`)),
  `video_url` varchar(500) DEFAULT NULL,
  `audio_url` varchar(500) DEFAULT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `author_id` int(10) unsigned DEFAULT NULL,
  `co_authors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`co_authors`)),
  `series_id` int(10) unsigned DEFAULT NULL,
  `series_order` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `article_type` varchar(30) NOT NULL DEFAULT 'standard',
  `reading_time_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `views_count` int(10) unsigned NOT NULL DEFAULT 0,
  `shares_count` int(10) unsigned NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_editors_pick` tinyint(1) NOT NULL DEFAULT 0,
  `is_fact_checked` tinyint(1) NOT NULL DEFAULT 0,
  `fact_checker_id` int(10) unsigned DEFAULT NULL,
  `allow_comments` tinyint(1) NOT NULL DEFAULT 1,
  `is_premium` tinyint(1) NOT NULL DEFAULT 0,
  `source_name` varchar(190) DEFAULT NULL,
  `source_url` varchar(1024) DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_articles_slug` (`slug`),
  KEY `idx_articles_category_id` (`category_id`),
  KEY `idx_articles_author_id` (`author_id`),
  KEY `idx_articles_series_id` (`series_id`),
  KEY `idx_articles_status_published` (`status`,`published_at`),
  CONSTRAINT `fk_articles_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_articles_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_articles_series` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=348 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: article_analytics
-- ---------------------------------------------------------
CREATE TABLE `article_analytics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `article_id` int(10) unsigned NOT NULL,
  `date` date NOT NULL,
  `views` int(10) unsigned NOT NULL DEFAULT 0,
  `unique_views` int(10) unsigned NOT NULL DEFAULT 0,
  `avg_read_time` int(10) unsigned NOT NULL DEFAULT 0,
  `scroll_depth_avg` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `shares` int(10) unsigned NOT NULL DEFAULT 0,
  `comments_count` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_article_analytics` (`article_id`,`date`),
  CONSTRAINT `fk_analytics_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: article_revisions
-- ---------------------------------------------------------
CREATE TABLE `article_revisions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `article_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `title_ar` varchar(255) DEFAULT NULL,
  `title_en` varchar(255) DEFAULT NULL,
  `content_ar` longtext DEFAULT NULL,
  `content_en` longtext DEFAULT NULL,
  `revision_note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_revisions_article` (`article_id`),
  CONSTRAINT `fk_revisions_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: tags
-- ---------------------------------------------------------
CREATE TABLE `tags` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `name_ar` varchar(100) DEFAULT NULL,
  `name_en` varchar(100) DEFAULT NULL,
  `slug` varchar(120) NOT NULL,
  `articles_count` int(10) unsigned NOT NULL DEFAULT 0,
  `is_trending` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tags_name` (`name`),
  UNIQUE KEY `uq_tags_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: article_tags
-- ---------------------------------------------------------
CREATE TABLE `article_tags` (
  `article_id` int(10) unsigned NOT NULL,
  `tag_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`article_id`,`tag_id`),
  KEY `fk_article_tags_tag` (`tag_id`),
  CONSTRAINT `fk_article_tags_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_article_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: bookmark_collections
-- ---------------------------------------------------------
CREATE TABLE `bookmark_collections` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_collections_user` (`user_id`),
  CONSTRAINT `fk_collections_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: bookmarks
-- ---------------------------------------------------------
CREATE TABLE `bookmarks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `article_id` int(10) unsigned NOT NULL,
  `collection_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bookmark` (`user_id`,`article_id`),
  KEY `fk_bookmarks_article` (`article_id`),
  KEY `fk_bookmarks_collection` (`collection_id`),
  CONSTRAINT `fk_bookmarks_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bookmarks_collection` FOREIGN KEY (`collection_id`) REFERENCES `bookmark_collections` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bookmarks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: bot_traffic_logs
-- ---------------------------------------------------------
CREATE TABLE `bot_traffic_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `visitor_type` varchar(50) NOT NULL DEFAULT 'human_visitor',
  `bot_name` varchar(100) NOT NULL DEFAULT 'Human',
  `ip_address` varchar(45) NOT NULL,
  `request_uri` varchar(500) NOT NULL,
  `http_method` varchar(10) NOT NULL DEFAULT 'GET',
  `status_code` smallint(5) unsigned NOT NULL DEFAULT 200,
  `user_agent` text DEFAULT NULL,
  `referer` varchar(500) DEFAULT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `response_time_ms` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_type` (`visitor_type`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2576 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: cache_settings
-- ---------------------------------------------------------
CREATE TABLE `cache_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(150) NOT NULL,
  `ttl_minutes` int(11) NOT NULL DEFAULT 60,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cache_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: comments
-- ---------------------------------------------------------
CREATE TABLE `comments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `article_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `guest_name` varchar(100) DEFAULT NULL,
  `guest_email` varchar(190) DEFAULT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `content` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `likes_count` int(10) unsigned NOT NULL DEFAULT 0,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `is_author_reply` tinyint(1) NOT NULL DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_comments_article_id` (`article_id`),
  KEY `idx_comments_user_id` (`user_id`),
  KEY `idx_comments_parent_id` (`parent_id`),
  CONSTRAINT `fk_comments_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: contact_messages
-- ---------------------------------------------------------
CREATE TABLE `contact_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `replied_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contact_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: cron_logs
-- ---------------------------------------------------------
CREATE TABLE `cron_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `job_name` varchar(100) NOT NULL DEFAULT 'rss_auto_publish',
  `status` enum('success','error','skipped') NOT NULL DEFAULT 'success',
  `message` text DEFAULT NULL,
  `articles_published` int(11) NOT NULL DEFAULT 0,
  `articles_skipped` int(11) NOT NULL DEFAULT 0,
  `articles_failed` int(11) NOT NULL DEFAULT 0,
  `sources_processed` int(11) NOT NULL DEFAULT 0,
  `execution_time_ms` int(11) NOT NULL DEFAULT 0,
  `triggered_by` enum('cron','manual') NOT NULL DEFAULT 'cron',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------
-- جدول: live_blogs
-- ---------------------------------------------------------
CREATE TABLE `live_blogs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `article_id` int(10) unsigned DEFAULT NULL,
  `title_ar` varchar(255) NOT NULL,
  `title_en` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ended_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_live_blog_article` (`article_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: live_blog_chat
-- ---------------------------------------------------------
CREATE TABLE `live_blog_chat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `live_blog_id` int(11) NOT NULL,
  `entry_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `sender_name` varchar(100) NOT NULL,
  `sender_avatar` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_blog_time` (`live_blog_id`,`created_at`),
  KEY `idx_blog_approved` (`live_blog_id`,`is_approved`),
  KEY `idx_chat_entry` (`entry_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: live_blog_entries
-- ---------------------------------------------------------
CREATE TABLE `live_blog_entries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `live_blog_id` int(10) unsigned NOT NULL,
  `author_id` int(10) unsigned DEFAULT NULL,
  `content_ar` longtext NOT NULL,
  `content_en` longtext DEFAULT NULL,
  `entry_type` varchar(30) NOT NULL DEFAULT 'text',
  `media_url` varchar(500) DEFAULT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_live_entries_blog` (`live_blog_id`),
  CONSTRAINT `fk_live_entries_blog` FOREIGN KEY (`live_blog_id`) REFERENCES `live_blogs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: live_blog_reactions
-- ---------------------------------------------------------
CREATE TABLE `live_blog_reactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `live_blog_id` int(11) NOT NULL,
  `entry_id` int(11) DEFAULT NULL,
  `reaction_type` varchar(30) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_blog_reaction` (`live_blog_id`,`reaction_type`),
  KEY `idx_entry_reaction` (`entry_id`,`reaction_type`)
) ENGINE=InnoDB AUTO_INCREMENT=180 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: media
-- ---------------------------------------------------------
CREATE TABLE `media` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `folder` varchar(190) NOT NULL DEFAULT 'default',
  `original_name` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `dimensions` varchar(50) DEFAULT NULL,
  `width` int(10) unsigned DEFAULT NULL,
  `height` int(10) unsigned DEFAULT NULL,
  `alt_text_ar` varchar(255) DEFAULT NULL,
  `alt_text_en` varchar(255) DEFAULT NULL,
  `thumbnail_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_media_folder` (`folder`),
  KEY `idx_media_user` (`user_id`),
  CONSTRAINT `fk_media_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: media_folders
-- ---------------------------------------------------------
CREATE TABLE `media_folders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_media_folders_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: menus
-- ---------------------------------------------------------
CREATE TABLE `menus` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `location` varchar(50) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_menus_location` (`location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: menu_items
-- ---------------------------------------------------------
CREATE TABLE `menu_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `menu_id` int(10) unsigned NOT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `label_ar` varchar(190) NOT NULL,
  `label_en` varchar(190) DEFAULT NULL,
  `title_ar` varchar(190) DEFAULT NULL,
  `title_en` varchar(190) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `target` varchar(20) NOT NULL DEFAULT '_self',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `icon` varchar(100) DEFAULT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_menu_items_menu` (`menu_id`),
  KEY `idx_menu_items_parent` (`parent_id`),
  CONSTRAINT `fk_menu_items_menu` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_menu_items_parent` FOREIGN KEY (`parent_id`) REFERENCES `menu_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: newsletters
-- ---------------------------------------------------------
CREATE TABLE `newsletters` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `unsubscribed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_newsletters_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: newsletter_campaigns
-- ---------------------------------------------------------
CREATE TABLE `newsletter_campaigns` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `subject_ar` varchar(255) DEFAULT NULL,
  `subject_en` varchar(255) DEFAULT NULL,
  `body_html` longtext NOT NULL,
  `content_ar` longtext DEFAULT NULL,
  `content_en` longtext DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `sent_count` int(10) unsigned NOT NULL DEFAULT 0,
  `opened_count` int(10) unsigned NOT NULL DEFAULT 0,
  `failed_count` int(10) unsigned NOT NULL DEFAULT 0,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_campaign_status` (`status`),
  KEY `fk_campaign_user` (`user_id`),
  CONSTRAINT `fk_campaign_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: notifications
-- ---------------------------------------------------------
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `type` varchar(50) NOT NULL,
  `title_ar` varchar(255) NOT NULL,
  `title_en` varchar(255) DEFAULT NULL,
  `message_ar` text DEFAULT NULL,
  `message_en` text DEFAULT NULL,
  `link` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`,`is_read`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: pages
-- ---------------------------------------------------------
CREATE TABLE `pages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title_ar` varchar(255) NOT NULL,
  `title_en` varchar(255) DEFAULT NULL,
  `slug` varchar(190) NOT NULL,
  `content_ar` longtext NOT NULL,
  `content_en` longtext DEFAULT NULL,
  `template` varchar(50) NOT NULL DEFAULT 'default',
  `meta_title_ar` varchar(255) DEFAULT NULL,
  `meta_title_en` varchar(255) DEFAULT NULL,
  `meta_description_ar` text DEFAULT NULL,
  `meta_description_en` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'published',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pages_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: password_resets
-- ---------------------------------------------------------
CREATE TABLE `password_resets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_password_resets_email` (`email`),
  KEY `idx_password_resets_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: plans
-- ---------------------------------------------------------
CREATE TABLE `plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name_ar` varchar(150) NOT NULL,
  `name_en` varchar(150) NOT NULL,
  `price_monthly` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_yearly` decimal(10,2) NOT NULL DEFAULT 0.00,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features`)),
  `max_articles_per_month` int(11) NOT NULL DEFAULT -1,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: polls
-- ---------------------------------------------------------
CREATE TABLE `polls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive','archived') DEFAULT 'active',
  `is_featured` tinyint(1) DEFAULT 1,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: poll_options
-- ---------------------------------------------------------
CREATE TABLE `poll_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `poll_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `icon` varchar(50) DEFAULT 'sparkle',
  `votes_count` int(11) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `poll_id` (`poll_id`),
  CONSTRAINT `fk_poll_options_poll` FOREIGN KEY (`poll_id`) REFERENCES `polls` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: poll_votes
-- ---------------------------------------------------------
CREATE TABLE `poll_votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `poll_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_hash` varchar(64) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `poll_id` (`poll_id`),
  KEY `ip_hash` (`ip_hash`),
  KEY `fk_poll_votes_option` (`option_id`),
  CONSTRAINT `fk_poll_votes_option` FOREIGN KEY (`option_id`) REFERENCES `poll_options` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_poll_votes_poll` FOREIGN KEY (`poll_id`) REFERENCES `polls` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: push_subscriptions
-- ---------------------------------------------------------
CREATE TABLE `push_subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `endpoint` text NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `content_encoding` varchar(30) NOT NULL DEFAULT 'aesgcm',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_push_user` (`user_id`),
  CONSTRAINT `fk_push_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: reactions
-- ---------------------------------------------------------
CREATE TABLE `reactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `article_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `ip_hash` varchar(64) DEFAULT NULL,
  `type` varchar(30) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reaction` (`article_id`,`user_id`,`type`),
  KEY `fk_reactions_user` (`user_id`),
  CONSTRAINT `fk_reactions_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: reading_history
-- ---------------------------------------------------------
CREATE TABLE `reading_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `article_id` int(10) unsigned NOT NULL,
  `read_percentage` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `time_spent_seconds` int(10) unsigned NOT NULL DEFAULT 0,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_reading_history_user` (`user_id`),
  KEY `idx_reading_history_article` (`article_id`),
  CONSTRAINT `fk_history_article` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: redirects
-- ---------------------------------------------------------
CREATE TABLE `redirects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `from_url` varchar(500) NOT NULL,
  `to_url` varchar(500) NOT NULL,
  `type` varchar(10) NOT NULL DEFAULT '301',
  `hits_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_redirects_from` (`from_url`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: reports
-- ---------------------------------------------------------
CREATE TABLE `reports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `reportable_type` varchar(50) NOT NULL,
  `reportable_id` int(10) unsigned NOT NULL,
  `reason` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_reports_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: rss_sources
-- ---------------------------------------------------------
CREATE TABLE `rss_sources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(190) NOT NULL,
  `url` varchar(500) NOT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `auto_fetch` tinyint(1) DEFAULT 1,
  `last_fetched_at` timestamp NULL DEFAULT NULL,
  `last_status` varchar(20) DEFAULT NULL COMMENT 'ok | empty | error',
  `last_http_code` int(11) DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `last_item_count` int(11) NOT NULL DEFAULT 0 COMMENT 'items found at the last health check',
  `last_checked_at` datetime DEFAULT NULL,
  `fail_count` int(11) NOT NULL DEFAULT 0,
  `new_items_count` int(11) NOT NULL DEFAULT 0 COMMENT 'fresh unpublished items count',
  `new_items_last_at` datetime DEFAULT NULL COMMENT 'latest fresh unpublished item time (UTC)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=232 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------
-- جدول: scheduled_tasks
-- ---------------------------------------------------------
CREATE TABLE `scheduled_tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `command` varchar(255) NOT NULL,
  `frequency` varchar(50) NOT NULL,
  `last_run_at` timestamp NULL DEFAULT NULL,
  `next_run_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: security_alerts
-- ---------------------------------------------------------
CREATE TABLE `security_alerts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `alert_type` varchar(50) NOT NULL,
  `severity` varchar(20) NOT NULL DEFAULT 'high',
  `ip_address` varchar(45) NOT NULL,
  `target_uri` varchar(500) NOT NULL,
  `payload_sample` text DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `is_blocked` tinyint(1) NOT NULL DEFAULT 0,
  `is_resolved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_severity` (`severity`),
  KEY `idx_type` (`alert_type`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: seo_meta
-- ---------------------------------------------------------
CREATE TABLE `seo_meta` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `model_type` varchar(80) NOT NULL,
  `model_id` int(10) unsigned NOT NULL,
  `meta_title_ar` varchar(255) DEFAULT NULL,
  `meta_title_en` varchar(255) DEFAULT NULL,
  `meta_description_ar` text DEFAULT NULL,
  `meta_description_en` text DEFAULT NULL,
  `og_image` varchar(500) DEFAULT NULL,
  `canonical_url` varchar(500) DEFAULT NULL,
  `robots` varchar(50) NOT NULL DEFAULT 'index,follow',
  `schema_markup` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`schema_markup`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_seo_model` (`model_type`,`model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: settings
-- ---------------------------------------------------------
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group` varchar(100) NOT NULL,
  `key` varchar(150) NOT NULL,
  `value` longtext DEFAULT NULL,
  `value_type` varchar(20) NOT NULL DEFAULT 'text',
  `label_ar` varchar(190) DEFAULT NULL,
  `label_en` varchar(190) DEFAULT NULL,
  `description_ar` text DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_group_key` (`group`,`key`),
  KEY `idx_settings_group` (`group`)
) ENGINE=InnoDB AUTO_INCREMENT=436 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: social_accounts
-- ---------------------------------------------------------
CREATE TABLE `social_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `provider` varchar(50) NOT NULL,
  `provider_id` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_social_provider_id` (`provider`,`provider_id`),
  KEY `idx_social_user` (`user_id`),
  CONSTRAINT `fk_social_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: subscriptions
-- ---------------------------------------------------------
CREATE TABLE `subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `plan_id` int(10) unsigned NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_subscriptions_user` (`user_id`),
  KEY `fk_subscriptions_plan` (`plan_id`),
  CONSTRAINT `fk_subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subscriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: translation_logs
-- ---------------------------------------------------------
CREATE TABLE `translation_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `article_id` int(10) unsigned DEFAULT NULL,
  `article_title_en` varchar(500) DEFAULT NULL,
  `provider` varchar(20) NOT NULL DEFAULT 'none',
  `status` varchar(20) NOT NULL DEFAULT 'failed',
  `http_code` smallint(5) unsigned DEFAULT NULL,
  `error_raw` text DEFAULT NULL,
  `error_summary` varchar(500) DEFAULT NULL,
  `response_preview` text DEFAULT NULL,
  `duration_ms` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=331 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------
-- جدول: tutorials
-- ---------------------------------------------------------
CREATE TABLE `tutorials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `summary` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `featured_image` varchar(1024) DEFAULT NULL,
  `difficulty` enum('beginner','intermediate','advanced') DEFAULT 'beginner',
  `estimated_minutes` int(11) DEFAULT 10,
  `author_id` int(11) DEFAULT NULL,
  `status` enum('draft','published') DEFAULT 'published',
  `views_count` int(11) DEFAULT 0,
  `published_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_tutorials_slug` (`slug`),
  KEY `idx_tutorials_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: tutorial_steps
-- ---------------------------------------------------------
CREATE TABLE `tutorial_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tutorial_id` int(11) NOT NULL,
  `step_number` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `image_caption` varchar(255) DEFAULT NULL,
  `content` longtext NOT NULL,
  `code_snippet` text DEFAULT NULL,
  `code_language` varchar(50) DEFAULT 'bash',
  `callout_type` enum('none','tip','warning','important','note') DEFAULT 'none',
  `callout_text` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_step_tutorial` (`tutorial_id`),
  KEY `idx_step_order` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- جدول: user_sessions
-- ---------------------------------------------------------
CREATE TABLE `user_sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `device_type` varchar(30) DEFAULT NULL,
  `location` varchar(190) DEFAULT NULL,
  `login_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `logout_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_sessions_user` (`user_id`),
  CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
