#ALTER TABLE ea_appointments ADD COLUMN RegBy int NOT NULL
#ALTER TABLE ea_appointments ADD CONSTRAINT FK_appointment_regby FOREIGN KEY (RegBy) REFERENCES ea_users (id)