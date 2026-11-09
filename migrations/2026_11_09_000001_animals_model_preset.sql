ALTER TABLE animals
    ADD COLUMN model_preset ENUM('aspin','puspin','hungarian','german_shepherd') NULL AFTER breed_type;
