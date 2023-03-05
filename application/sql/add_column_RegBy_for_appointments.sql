ALTER TABLE ea_appointments ADD COLUMN reg_by int NOT NULL
ALTER TABLE ea_appointments ADD CONSTRAINT FK_appointment_regby FOREIGN KEY (reg_by) REFERENCES ea_users (id)