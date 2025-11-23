-- 添加用户信息字段：性别、出生日期、星座、属相、人格
ALTER TABLE `users`
  ADD COLUMN `gender` VARCHAR(10) NULL DEFAULT NULL COMMENT '性别：male/female/other' AFTER `nickname`,
  ADD COLUMN `birth_date` DATE NULL DEFAULT NULL COMMENT '出生年月日' AFTER `gender`,
  ADD COLUMN `zodiac` VARCHAR(20) NULL DEFAULT NULL COMMENT '星座' AFTER `birth_date`,
  ADD COLUMN `chinese_zodiac` VARCHAR(20) NULL DEFAULT NULL COMMENT '属相' AFTER `zodiac`,
  ADD COLUMN `personality` VARCHAR(100) NULL DEFAULT NULL COMMENT '人格' AFTER `chinese_zodiac`;

