SET
  CHARACTER_SET_CLIENT = utf8mb4;

SET
  CHARACTER_SET_CONNECTION = utf8mb4;

USE isuride;

-- システム設定テーブル
DROP TABLE IF EXISTS settings;

CREATE TABLE
  settings (
    name VARCHAR(30) NOT NULL COMMENT '設定名',
    value TEXT NOT NULL COMMENT '設定値',
    PRIMARY KEY (name)
  ) COMMENT = 'システム設定テーブル';

-- 椅子モデルテーブル
DROP TABLE IF EXISTS chair_models;

CREATE TABLE
  chair_models (
    name VARCHAR(50) NOT NULL COMMENT '椅子モデル名',
    speed INTEGER NOT NULL COMMENT '移動速度',
    PRIMARY KEY (name)
  ) COMMENT = '椅子モデルテーブル';

-- 椅子情報テーブル
DROP TABLE IF EXISTS chairs;

CREATE TABLE
  chairs (
    id VARCHAR(26) NOT NULL COMMENT '椅子ID',
    owner_id VARCHAR(26) NOT NULL COMMENT 'オーナーID',
    name VARCHAR(30) NOT NULL COMMENT '椅子の名前',
    model VARCHAR(50) NOT NULL COMMENT '椅子のモデル',
    is_active TINYINT (1) NOT NULL COMMENT '配椅子受付中かどうか',
    access_token VARCHAR(255) NOT NULL COMMENT 'アクセストークン',
    created_at DATETIME (6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '登録日時',
    updated_at DATETIME (6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新日時',
    PRIMARY KEY (id),
    INDEX idx_owner_id (owner_id),
    INDEX idx_is_active (is_active),
    INDEX idx_created_at (created_at),
    INDEX idx_updated_at (updated_at)
  ) COMMENT = '椅子情報テーブル';

-- 椅子の現在位置情報テーブル
DROP TABLE IF EXISTS chair_locations;

CREATE TABLE
  chair_locations (
    id VARCHAR(26) NOT NULL,
    chair_id VARCHAR(26) NOT NULL COMMENT '椅子ID',
    latitude INTEGER NOT NULL COMMENT '経度',
    longitude INTEGER NOT NULL COMMENT '緯度',
    created_at DATETIME (6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '登録日時',
    PRIMARY KEY (id),
    INDEX idx_chair_id_created_at (chair_id, created_at DESC)
  ) COMMENT = '椅子の現在位置情報テーブル';

-- 利用者情報テーブル
DROP TABLE IF EXISTS users;

CREATE TABLE
  users (
    id VARCHAR(26) NOT NULL COMMENT 'ユーザーID',
    username VARCHAR(30) NOT NULL COMMENT 'ユーザー名',
    firstname VARCHAR(30) NOT NULL COMMENT '本名(名前)',
    lastname VARCHAR(30) NOT NULL COMMENT '本名(名字)',
    date_of_birth VARCHAR(30) NOT NULL COMMENT '生年月日',
    access_token VARCHAR(255) NOT NULL COMMENT 'アクセストークン',
    invitation_code VARCHAR(30) NOT NULL COMMENT '招待トークン',
    created_at DATETIME (6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '登録日時',
    updated_at DATETIME (6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新日時',
    PRIMARY KEY (id),
    UNIQUE (username),
    UNIQUE (access_token),
    UNIQUE (invitation_code),
    INDEX idx_created_at (created_at),
    INDEX idx_updated_at (updated_at)
  ) COMMENT = '利用者情報テーブル';

-- 決済トークンテーブル
DROP TABLE IF EXISTS payment_tokens;

CREATE TABLE
  payment_tokens (
    user_id VARCHAR(26) NOT NULL COMMENT 'ユーザーID',
    token VARCHAR(255) NOT NULL COMMENT '決済トークン',
    created_at DATETIME (6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '登録日時',
    PRIMARY KEY (user_id),
    INDEX idx_created_at (created_at)
  ) COMMENT = '決済トークンテーブル';

-- ライド情報テーブル
DROP TABLE IF EXISTS rides;

CREATE TABLE
  rides (
    id VARCHAR(26) NOT NULL COMMENT 'ライドID',
    user_id VARCHAR(26) NOT NULL COMMENT 'ユーザーID',
    chair_id VARCHAR(26) DEFAULT NULL COMMENT '割り当てられた椅子ID',
    pickup_latitude INTEGER NOT NULL COMMENT '配車位置(経度)',
    pickup_longitude INTEGER NOT NULL COMMENT '配車位置(緯度)',
    destination_latitude INTEGER NOT NULL COMMENT '目的地(経度)',
    destination_longitude INTEGER NOT NULL COMMENT '目的地(緯度)',
    evaluation INTEGER DEFAULT NULL COMMENT '評価',
    created_at DATETIME (6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '要求日時',
    updated_at DATETIME (6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '状態更新日時',
    status ENUM (
      'MATCHING',
      'ENROUTE',
      'PICKUP',
      'CARRYING',
      'ARRIVED',
      'COMPLETED'
    ) DEFAULT 'MATCHING' NOT NULL COMMENT '状態',
    PRIMARY KEY (id),
    INDEX idx_user_id_created_at (user_id, created_at),
    INDEX idx_chair_id (chair_id),
    INDEX idx_status_id (status),
    INDEX idx_created_at_desc (created_at DESC),
    INDEX idx_updated_at_desc (updated_at DESC)
  ) COMMENT = 'ライド情報テーブル';

-- ライドステータスの変更履歴テーブル
DROP TABLE IF EXISTS ride_statuses;

CREATE TABLE
  ride_statuses (
    id VARCHAR(26) NOT NULL,
    ride_id VARCHAR(26) NOT NULL COMMENT 'ライドID',
    status ENUM (
      'MATCHING',
      'ENROUTE',
      'PICKUP',
      'CARRYING',
      'ARRIVED',
      'COMPLETED'
    ) NOT NULL COMMENT '状態',
    created_at DATETIME (6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '状態変更日時',
    app_sent_at DATETIME (6) DEFAULT NULL COMMENT 'ユーザーへの状態通知日時',
    chair_sent_at DATETIME (6) DEFAULT NULL COMMENT '椅子への状態通知日時',
    PRIMARY KEY (id),
    INDEX idx_ride_id_status (ride_id, status),
    INDEX idx_created_at_desc (created_at DESC)
  ) COMMENT = 'ライドステータスの変更履歴テーブル';

-- 椅子のオーナー情報テーブル
DROP TABLE IF EXISTS owners;

CREATE TABLE
  owners (
    id VARCHAR(26) NOT NULL COMMENT 'オーナーID',
    name VARCHAR(30) NOT NULL COMMENT 'オーナー名',
    access_token VARCHAR(255) NOT NULL COMMENT 'アクセストークン',
    chair_register_token VARCHAR(255) NOT NULL COMMENT '椅子登録トークン',
    created_at DATETIME (6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '登録日時',
    updated_at DATETIME (6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '更新日時',
    PRIMARY KEY (id),
    UNIQUE (name),
    UNIQUE (access_token),
    UNIQUE (chair_register_token),
    INDEX idx_created_at (created_at),
    INDEX idx_updated_at (updated_at)
  ) COMMENT = '椅子のオーナー情報テーブル';

-- クーポンテーブル
DROP TABLE IF EXISTS coupons;

CREATE TABLE
  coupons (
    user_id VARCHAR(26) NOT NULL COMMENT '所有しているユーザーのID',
    code VARCHAR(255) NOT NULL COMMENT 'クーポンコード',
    discount INTEGER NOT NULL COMMENT '割引額',
    created_at DATETIME (6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '付与日時',
    used_by VARCHAR(26) DEFAULT NULL COMMENT 'クーポンが適用されたライドのID',
    PRIMARY KEY (user_id, code),
    INDEX idx_user_id_created_at (user_id, created_at),
    INDEX idx_used_by (used_by)
  ) COMMENT 'クーポンテーブル';

DROP VIEW IF EXISTS last_chair_locations;
CREATE ALGORITHM = UNDEFINED DEFINER = `isucon` @`%` SQL SECURITY DEFINER VIEW `last_chair_locations` AS
select
    `chair_locations`.`chair_id` AS `chair_id`,
    substr(
        max(
            concat(
                `chair_locations`.`created_at`,
                `chair_locations`.`id`
            )
        ),
        27
    ) AS `last_id`,
    substr(
        max(
            concat(
                `chair_locations`.`created_at`,
                `chair_locations`.`latitude`
            )
        ),
        27
    ) AS `last_latitude`,
    substr(
        max(
            concat(
                `chair_locations`.`created_at`,
                `chair_locations`.`longitude`
            )
        ),
        27
    ) AS `last_longitude`,
    max(created_at) AS last_created_at
from `chair_locations`
group by
    `chair_locations`.`chair_id`;

DROP VIEW IF EXISTS complete_rides;
CREATE ALGORITHM = UNDEFINED DEFINER = `isucon` @`%` SQL SECURITY DEFINER VIEW `complete_rides` AS
select
    `ride_statuses`.`ride_id` AS `ride_id`,
    count(`ride_statuses`.`chair_sent_at`) = 6 AS completed
from `ride_statuses`
group by
    `ride_statuses`.`ride_id`;