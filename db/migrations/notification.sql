-- Create notifications table and triggers (prevents duplicate notifications)

CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `message` VARCHAR(255) NOT NULL,
  `reference_id` VARCHAR(32) DEFAULT NULL,
  `notification_type` VARCHAR(50) NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  KEY `reference_id` (`reference_id`),
  -- prevent exact duplicate notifications for same user/ref/type/message
  UNIQUE KEY `uniq_notification` (`user_id`, `reference_id`, `notification_type`, `message`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TRIGGER IF EXISTS `order_status_notification`;
DROP TRIGGER IF EXISTS `payment_status_notification`;
DROP TRIGGER IF EXISTS `delivery_status_notification`;

DELIMITER $$
CREATE TRIGGER `order_status_notification`
AFTER UPDATE ON `orders`
FOR EACH ROW
BEGIN
  DECLARE v_user INT;
  DECLARE v_msg VARCHAR(255);
  DECLARE v_ref VARCHAR(32);

  IF NEW.order_status_id <> OLD.order_status_id THEN
    -- avoid duplicates caused by delivery flow: skip if delivery already handling (batch check)
    IF NOT (NEW.order_status_id IN (2,3) AND EXISTS (
      SELECT 1 FROM deliveries d WHERE d.batch_id = NEW.batch_id AND d.delivery_status_id IN (2,3)
    )) THEN
      SELECT a.account_id INTO v_user
        FROM accounts a
        WHERE a.customer_id = NEW.customer_id
        LIMIT 1;
      SET v_ref = NEW.reference_id;
      IF v_user IS NOT NULL THEN
        SET v_msg = CONCAT('Your order with tracking number #', v_ref, ' has been ',
          CASE
            WHEN NEW.order_status_id = 1 THEN 'received and is being processed'
            WHEN NEW.order_status_id = 2 THEN 'dispatched and is on its way'
            WHEN NEW.order_status_id = 3 THEN 'successfully delivered'
            WHEN NEW.order_status_id = 4 THEN 'marked as failed. Please contact support'
            ELSE CONCAT('updated to status id ', NEW.order_status_id)
          END, '.');
        INSERT IGNORE INTO notifications (user_id, message, reference_id, notification_type)
          VALUES (v_user, v_msg, v_ref, 'order_status');
      END IF;
    END IF;
  END IF;
END$$

CREATE TRIGGER `payment_status_notification`
AFTER UPDATE ON `payments`
FOR EACH ROW
BEGIN
  DECLARE v_user INT;
  DECLARE v_msg VARCHAR(255);
  DECLARE v_ref VARCHAR(32);

  IF NEW.payment_status_id <> OLD.payment_status_id THEN
    SELECT a.account_id INTO v_user
      FROM orders o
      JOIN accounts a ON a.customer_id = o.customer_id
      WHERE o.reference_id = NEW.reference_id
      LIMIT 1;
    SET v_ref = NEW.reference_id;
    IF v_user IS NOT NULL THEN
      SET v_msg = CONCAT('Payment for order #', v_ref, ' has been ',
        CASE
          WHEN NEW.payment_status_id = 1 THEN 'received and is being verified'
          WHEN NEW.payment_status_id = 2 THEN 'confirmed and processed successfully'
          WHEN NEW.payment_status_id = 3 THEN 'declined. Please update your payment information'
          ELSE CONCAT('updated to payment status id ', NEW.payment_status_id)
        END, '.');
      INSERT IGNORE INTO notifications (user_id, message, reference_id, notification_type)
        VALUES (v_user, v_msg, v_ref, 'payment_status');
    END IF;
  END IF;
END$$

CREATE TRIGGER `delivery_status_notification`
AFTER UPDATE ON `deliveries`
FOR EACH ROW
BEGIN
  DECLARE v_user INT;
  DECLARE v_msg VARCHAR(255);
  DECLARE v_ref VARCHAR(32);

  IF NEW.delivery_status_id <> OLD.delivery_status_id THEN
    -- notify only for important transitions (e.g. dispatched=2, delivered=3)
    IF NEW.delivery_status_id IN (2,3) THEN
      SELECT o.reference_id, a.account_id INTO v_ref, v_user
        FROM orders o
        JOIN accounts a ON a.customer_id = o.customer_id
        WHERE o.batch_id = NEW.batch_id
        LIMIT 1;
      IF v_user IS NOT NULL THEN
        SET v_msg = CONCAT(
          CASE WHEN NEW.delivery_type = 'pickup' THEN 'Pickup for order #' ELSE 'Delivery for order #' END,
          v_ref,
          CASE
            WHEN NEW.delivery_status_id = 2 THEN ' has started and is on its way.'
            WHEN NEW.delivery_status_id = 3 THEN ' has been completed successfully.'
            ELSE CONCAT(' updated to delivery status id ', NEW.delivery_status_id, '.')
          END
        );
        INSERT IGNORE INTO notifications (user_id, message, reference_id, notification_type)
          VALUES (v_user, v_msg, v_ref, 'delivery_status');
      END IF;
    END IF;
  END IF;
END$$
DELIMITER ;