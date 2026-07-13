DROP TRIGGER IF EXISTS wonder_audit_no_delete ON wonder_audit_events;
DROP TRIGGER IF EXISTS wonder_audit_no_update ON wonder_audit_events;
DROP FUNCTION IF EXISTS wonder_audit_immutable();
DROP TABLE IF EXISTS wonder_audit_events;