<?php
defined('AULA') || exit;

/**
 * Migraciones del núcleo. Nunca modifiques una versión ya publicada:
 * añade una nueva (2 => [...]) con los cambios.
 */
return [
    1 => [
        'CREATE TABLE settings (name VARCHAR(100) NOT NULL PRIMARY KEY, value {TEXT}){TABLE_OPTS}',

        "CREATE TABLE users (id {PK}, email VARCHAR(190) NOT NULL, name VARCHAR(150) NOT NULL, password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'student', status VARCHAR(20) NOT NULL DEFAULT 'active', prefs {TEXT},
            created_at INT NOT NULL DEFAULT 0, last_login_at INT NOT NULL DEFAULT 0, last_seen_at INT NOT NULL DEFAULT 0){TABLE_OPTS}",
        'CREATE UNIQUE INDEX users_email ON users (email)',

        'CREATE TABLE courses (id {PK}, title VARCHAR(200) NOT NULL, summary {TEXT}, enrol_code VARCHAR(40) NOT NULL,
            enrol_open INT NOT NULL DEFAULT 1, visible INT NOT NULL DEFAULT 1, sort INT NOT NULL DEFAULT 0,
            created_at INT NOT NULL DEFAULT 0, updated_at INT NOT NULL DEFAULT 0){TABLE_OPTS}',
        'CREATE UNIQUE INDEX courses_code ON courses (enrol_code)',

        'CREATE TABLE enrolments (id {PK}, course_id INT NOT NULL, user_id INT NOT NULL, created_at INT NOT NULL DEFAULT 0){TABLE_OPTS}',
        'CREATE UNIQUE INDEX enrolments_course_user ON enrolments (course_id, user_id)',
        'CREATE INDEX enrolments_user ON enrolments (user_id)',

        'CREATE TABLE sections (id {PK}, course_id INT NOT NULL, title VARCHAR(200) NOT NULL, summary {TEXT},
            sort INT NOT NULL DEFAULT 0, visible INT NOT NULL DEFAULT 1){TABLE_OPTS}',
        'CREATE INDEX sections_course ON sections (course_id)',

        'CREATE TABLE resources (id {PK}, course_id INT NOT NULL, section_id INT NOT NULL, type VARCHAR(40) NOT NULL,
            title VARCHAR(200) NOT NULL, description {TEXT}, data {TEXT}, sort INT NOT NULL DEFAULT 0, visible INT NOT NULL DEFAULT 1,
            created_at INT NOT NULL DEFAULT 0, updated_at INT NOT NULL DEFAULT 0){TABLE_OPTS}',
        'CREATE INDEX resources_section ON resources (section_id)',
        'CREATE INDEX resources_course ON resources (course_id)',

        'CREATE TABLE resource_views (id {PK}, resource_id INT NOT NULL, user_id INT NOT NULL,
            first_at INT NOT NULL DEFAULT 0, last_at INT NOT NULL DEFAULT 0, views INT NOT NULL DEFAULT 0){TABLE_OPTS}',
        'CREATE UNIQUE INDEX resource_views_res_user ON resource_views (resource_id, user_id)',

        'CREATE TABLE files (id {PK}, user_id INT NOT NULL DEFAULT 0, context VARCHAR(40) NOT NULL, context_id INT NOT NULL DEFAULT 0,
            name VARCHAR(255) NOT NULL, mime VARCHAR(120) NOT NULL, size BIGINT NOT NULL DEFAULT 0, storage VARCHAR(255) NOT NULL,
            created_at INT NOT NULL DEFAULT 0){TABLE_OPTS}',
        'CREATE INDEX files_context ON files (context, context_id)',
        'CREATE INDEX files_storage ON files (storage)',

        'CREATE TABLE password_resets (id {PK}, user_id INT NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at INT NOT NULL,
            used INT NOT NULL DEFAULT 0, created_at INT NOT NULL DEFAULT 0){TABLE_OPTS}',
        'CREATE INDEX password_resets_token ON password_resets (token_hash)',

        'CREATE TABLE login_attempts (id {PK}, ip VARCHAR(64) NOT NULL, email VARCHAR(190) NOT NULL, created_at INT NOT NULL){TABLE_OPTS}',
        'CREATE INDEX login_attempts_ip ON login_attempts (ip, created_at)',
        'CREATE INDEX login_attempts_email ON login_attempts (email, created_at)',
    ],
    // Portada de los cursos: imagen subida o diseño de serie.
    2 => [
        'ALTER TABLE courses ADD COLUMN cover_file_id INT NOT NULL DEFAULT 0',
        "ALTER TABLE courses ADD COLUMN cover_style VARCHAR(20) NOT NULL DEFAULT ''",
    ],
];
