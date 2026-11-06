ALTER TABLE reports
    ADD COLUMN species ENUM('dog','cat') NULL AFTER animal_description;
