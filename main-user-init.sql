\connect app;
GRANT ALL ON SCHEMA public TO app_user;
GRANT ALL ON ALL TABLES IN SCHEMA public TO app_user;
GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO app_user;
GRANT ALL ON ALL PROCEDURES IN SCHEMA public TO app_user;
GRANT ALL ON ALL ROUTINES IN SCHEMA public TO app_user;
GRANT ALL ON ALL FUNCTIONS IN SCHEMA public TO app_user;

ALTER SCHEMA application OWNER TO app_user;
ALTER SCHEMA entity_cache OWNER TO app_user;

ALTER TABLE entity_cache.cached_classifier_values OWNER TO app_user;
ALTER TABLE entity_cache.cached_institutions OWNER TO app_user;
ALTER TABLE entity_cache.cached_institution_users OWNER TO app_user;


\connect testing;
GRANT ALL ON SCHEMA public TO app_user;
GRANT ALL ON ALL TABLES IN SCHEMA public TO app_user;
GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO app_user;
GRANT ALL ON ALL PROCEDURES IN SCHEMA public TO app_user;
GRANT ALL ON ALL ROUTINES IN SCHEMA public TO app_user;
GRANT ALL ON ALL FUNCTIONS IN SCHEMA public TO app_user;

ALTER SCHEMA application OWNER TO app_user;
ALTER SCHEMA entity_cache OWNER TO app_user;
ALTER TABLE entity_cache.cached_classifier_values OWNER TO app_user;
ALTER TABLE entity_cache.cached_institutions OWNER TO app_user;
ALTER TABLE entity_cache.cached_institution_users OWNER TO app_user;
