CREATE DATABASE IF NOT EXISTS sampoerna_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sampoerna_inventory;

CREATE TABLE users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 username VARCHAR(50) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE items (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 sku VARCHAR(50) NOT NULL UNIQUE,
 category VARCHAR(80) NOT NULL,
 unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
 stock INT NOT NULL DEFAULT 0,
 min_stock INT NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE transactions (
 id INT AUTO_INCREMENT PRIMARY KEY,
 item_id INT NOT NULL,
 type ENUM('IN','OUT') NOT NULL,
 quantity INT NOT NULL,
 note VARCHAR(255),
 user_id INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_transaction_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
 CONSTRAINT fk_transaction_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE productions (
 id INT AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(50) NOT NULL UNIQUE,
 product_name VARCHAR(150) NOT NULL,
 quantity INT NOT NULL,
 planned_date DATE NOT NULL,
 status ENUM('Planned','In Progress','Completed') NOT NULL DEFAULT 'Planned',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Demo login: admin / admin123
INSERT INTO users(name,username,password) VALUES
('Vera Rizkiy','admin','$2y$12$Xn.ql5s3ajewJf5EhYtxxefOmRi.rJMO9hWobkhXSh1x5aETpmWqO');

INSERT INTO items(name,sku,category,unit,stock,min_stock) VALUES
('Tobacco Leaf Grade A','RM-001','Raw Material','kg',420,100),
('Cigarette Paper Roll','RM-002','Raw Material','roll',68,80),
('Filter Rod','RM-003','Raw Material','box',155,50),
('Packaging Carton','PK-001','Packaging','pcs',34,60),
('Printed Wrapper','PK-002','Packaging','roll',210,40),
('Finished Product Alpha','FG-001','Finished Goods','carton',860,200),
('Finished Product Beta','FG-002','Finished Goods','carton',540,150);

INSERT INTO transactions(item_id,type,quantity,note,user_id) VALUES
(1,'IN',500,'Initial stock receipt',1),(2,'OUT',32,'Production usage',1),
(3,'IN',100,'Supplier delivery',1),(4,'OUT',16,'Packaging line usage',1),
(6,'IN',300,'Production output',1),(5,'IN',80,'Supplier delivery',1);

INSERT INTO productions(code,product_name,quantity,planned_date,status) VALUES
('PRD-2026-021','Finished Product Alpha',1200,'2026-11-05','In Progress'),
('PRD-2026-022','Finished Product Beta',850,'2026-11-08','Planned'),
('PRD-2026-020','Finished Product Alpha',950,'2026-10-28','Completed');
