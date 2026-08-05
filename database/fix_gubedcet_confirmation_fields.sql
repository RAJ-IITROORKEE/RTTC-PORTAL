-- Run once on databases created before the GUBEDCET gender and booklet fields.
ALTER TABLE `academic_details`
    ADD COLUMN IF NOT EXISTS `gubedcet_gender` VARCHAR(20) NULL AFTER `gubedcet_category`,
    ADD COLUMN IF NOT EXISTS `gubedcet_booklet_series` VARCHAR(10) NULL AFTER `gubedcet_gender`;
