-- Audit table for administrator payment-record deletions.
CREATE TABLE IF NOT EXISTS `payment_deletion_log` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id`            INT(11)        NOT NULL,
  `user_id`               INT(11)        NOT NULL,
  `razorpay_order_id`     VARCHAR(100)   NOT NULL,
  `razorpay_payment_id`   VARCHAR(100)            DEFAULT NULL,
  `amount`                INT(11)        NOT NULL,
  `currency`              VARCHAR(5)     NOT NULL,
  `status`                VARCHAR(20)    NOT NULL,
  `deleted_by_admin_id`   INT(11)        NOT NULL,
  `deleted_at`            DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payment_deletion_payment` (`payment_id`),
  KEY `idx_payment_deletion_user` (`user_id`),
  KEY `idx_payment_deletion_admin` (`deleted_by_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Repair rows left at Payment after a payment row was removed manually.
-- Personal, academic, and document tables are not modified.
UPDATE registration_progress rp
LEFT JOIN payment p ON p.user_id = rp.user_id AND p.status = 'success'
SET rp.current_step = 3, rp.is_submitted = 0
WHERE rp.current_step >= 4 AND p.user_id IS NULL;
