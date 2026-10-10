#!/usr/bin/env python3
"""Compare a restored PostgreSQL catalog with the captured application schema."""
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCHEMAS = {'public', 'private'}


def text_array_casts(value):
    # PostgreSQL reparses an array-level varchar[] -> text[] coercion into
    # per-element coercions. Canonicalize ONLY literal varchar/text arrays;
    # do not discard arbitrary casts or modify function bodies.
    value = re.sub(r'\((ARRAY\[[^\]]+\])\)::text\[\]', r'\1::text[]', value)
    pattern = r"ARRAY\[(?:\(?'(?:[^']|'')*'::character varying\)?(?:::text)?(?:, )?)+\](?:::text\[\])?"

    def replace(match):
        array = match.group(0)
        if not (array.endswith('::text[]') or '::text' in array):
            return array
        array = re.sub(r"\(('(?:[^']|'')*'::character varying)\)::text", r'\1::text', array)
        array = array.replace('::character varying::text', '::text').replace('::character varying', '::text')
        array = re.sub(r'::text\[\]$', '', array)
        return array

    return re.sub(pattern, replace, value)


def normalize(value):
    if isinstance(value, str):
        return value.replace('\r\n', '\n')
    if isinstance(value, list):
        return [normalize(v) for v in value]
    if isinstance(value, dict):
        if 'acl' in value and value['acl'] is None and 'owner' in value:
            value = dict(value)
            owner = value['owner']
            if value.get('definition', '').startswith('CREATE OR REPLACE FUNCTION'):
                value['acl'] = [f'=X/{owner}', f'{owner}=X/{owner}']
            elif 'increment' in value:
                value['acl'] = [f'{owner}=rwU/{owner}']
            elif 'kind' in value:
                value['acl'] = [f'{owner}=arwdDxtm/{owner}']
            elif set(value) == {'name', 'owner', 'acl'}:
                value['acl'] = [f'{owner}=UC/{owner}']
        return {k: (sorted(v) if k == 'acl' and v else
                    text_array_casts(normalize(v)) if isinstance(v, str) and k in {'definition', 'qual', 'with_check'}
                    and not v.startswith('CREATE OR REPLACE FUNCTION') else normalize(v))
                for k, v in value.items()}
    return value


def application(catalog):
    # Managed platform structures are inventoried but provided by Supabase, or
    # minimal platform stubs in this isolated PostgreSQL restore verification.
    result = {}
    for group in ['tables', 'sequences', 'routines']:
        result[group] = [x for x in catalog[group] or [] if x['schema'] in SCHEMAS]
    result['triggers'] = [x for x in catalog['triggers'] or []
                          if x['schema'] in SCHEMAS or x['function_schema'] in SCHEMAS]
    result['policies'] = [x for x in catalog['policies'] or [] if x['schemaname'] in SCHEMAS | {'storage'}]
    result['default_acl'] = sorted(catalog['default_acl'] or [], key=lambda x: (x['schema'], x['owner'], x['type']))
    result['schemas'] = [x for x in catalog['schemas'] if x['name'] in SCHEMAS]
    result['publications'] = [x for x in catalog['publications'] or []
                              if any(t['schemaname'] in SCHEMAS for t in x['tables'] or [])]
    return normalize(result)


if __name__ == '__main__':
    expected = application(json.loads((ROOT / 'database/schema/supabase-catalog.json').read_text()))
    actual = application(json.loads(Path(sys.argv[1]).read_text()))
    failed = []
    for group in expected:
        if expected[group] != actual[group]:
            failed.append(group)
            # Metadata contains no rows, but limit output to differing objects.
            for i, left in enumerate(expected[group] or []):
                right = (actual[group] or [])[i] if i < len(actual[group] or []) else None
                if left != right:
                    print(group, 'expected:', json.dumps(left, ensure_ascii=False))
                    print(group, 'actual:', json.dumps(right, ensure_ascii=False))
                    break
    if failed:
        raise SystemExit('Restored schema differs: ' + ', '.join(failed))
    print('PASS: restored schema matches live-captured tables/columns, constraints, indexes,')
    print('      sequences, views, routines, triggers, RLS policies, ACLs and publications')
