-- Read-only companion capture; expands implicit ACL defaults without exposing data.
SELECT jsonb_build_object(
  'objects', (SELECT jsonb_agg(x ORDER BY x->>'schema', x->>'kind', x->>'name', x->>'grantee', x->>'privilege') FROM (
    SELECT jsonb_build_object('schema', n.nspname, 'kind', CASE WHEN c.relkind='S' THEN 'SEQUENCE' ELSE 'TABLE' END,
      'name', format('%I.%I', n.nspname, c.relname),
      'grantee', CASE WHEN a.grantee=0 THEN 'PUBLIC' ELSE pg_get_userbyid(a.grantee) END,
      'privilege', a.privilege_type, 'grantable', a.is_grantable) AS x
    FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace
    CROSS JOIN LATERAL aclexplode(coalesce(c.relacl, acldefault(CASE WHEN c.relkind='S' THEN 's'::"char" ELSE 'r'::"char" END, c.relowner))) a
    WHERE n.nspname IN ('public','private') AND c.relkind IN ('r','p','v','m','S')
    UNION ALL
    SELECT jsonb_build_object('schema', n.nspname, 'kind', 'COLUMN',
      'name', format('%I.%I', n.nspname, c.relname), 'column', attr.attname,
      'grantee', CASE WHEN a.grantee=0 THEN 'PUBLIC' ELSE pg_get_userbyid(a.grantee) END,
      'privilege', a.privilege_type, 'grantable', a.is_grantable)
    FROM pg_attribute attr JOIN pg_class c ON c.oid=attr.attrelid JOIN pg_namespace n ON n.oid=c.relnamespace
    CROSS JOIN LATERAL aclexplode(attr.attacl) a
    WHERE n.nspname IN ('public','private') AND attr.attnum>0 AND NOT attr.attisdropped
    UNION ALL
    SELECT jsonb_build_object('schema', n.nspname, 'kind', 'FUNCTION',
      'name', format('%I.%I(%s)', n.nspname, p.proname, pg_get_function_identity_arguments(p.oid)),
      'grantee', CASE WHEN a.grantee=0 THEN 'PUBLIC' ELSE pg_get_userbyid(a.grantee) END,
      'privilege', a.privilege_type, 'grantable', a.is_grantable)
    FROM pg_proc p JOIN pg_namespace n ON n.oid=p.pronamespace
    CROSS JOIN LATERAL aclexplode(coalesce(p.proacl, acldefault('f', p.proowner))) a
    WHERE n.nspname IN ('public','private')
      AND NOT EXISTS (SELECT 1 FROM pg_depend d WHERE d.classid='pg_proc'::regclass AND d.objid=p.oid AND d.deptype='e')
    UNION ALL
    SELECT jsonb_build_object('schema', n.nspname, 'kind', 'SCHEMA', 'name', quote_ident(n.nspname),
      'grantee', CASE WHEN a.grantee=0 THEN 'PUBLIC' ELSE pg_get_userbyid(a.grantee) END,
      'privilege', a.privilege_type, 'grantable', a.is_grantable)
    FROM pg_namespace n CROSS JOIN LATERAL aclexplode(coalesce(n.nspacl, acldefault('n', n.nspowner))) a
    WHERE n.nspname IN ('public','private')
  ) q),
  'defaults', (SELECT jsonb_agg(jsonb_build_object('schema', n.nspname, 'owner', pg_get_userbyid(d.defaclrole),
    'type', d.defaclobjtype, 'grantee', CASE WHEN a.grantee=0 THEN 'PUBLIC' ELSE pg_get_userbyid(a.grantee) END,
    'privilege', a.privilege_type, 'grantable', a.is_grantable))
    FROM pg_default_acl d JOIN pg_namespace n ON n.oid=d.defaclnamespace CROSS JOIN LATERAL aclexplode(d.defaclacl) a
    WHERE n.nspname IN ('public','private'))
) AS grants;
