CREATE TABLE ebook_creation (
    id INT AUTO_INCREMENT NOT NULL,
    job_id INT NOT NULL,
    resource_data VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
