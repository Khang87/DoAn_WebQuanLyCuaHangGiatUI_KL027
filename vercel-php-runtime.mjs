import { access, readFile, writeFile } from 'node:fs/promises';
import { join } from 'node:path';

const runtimeCandidates = [
    join(process.cwd(), '.vercel', 'builders', 'node_modules', 'vercel-php'),
    join(process.cwd(), 'node_modules', 'vercel-php'),
];

let runtimeDirectory;

for (const candidate of runtimeCandidates) {
    try {
        await access(candidate);
        runtimeDirectory = candidate;
        break;
    } catch (error) {
        if (error.code !== 'ENOENT') {
            throw error;
        }
    }
}

if (!runtimeDirectory) {
    throw new Error('The vercel-php builder was not installed before the build command.');
}

const packageJson = JSON.parse(await readFile(join(runtimeDirectory, 'package.json'), 'utf8'));

if (packageJson.version !== '0.9.0') {
    throw new Error(`Unsupported vercel-php version ${packageJson.version}; expected 0.9.0.`);
}

const indexPath = join(runtimeDirectory, 'dist', 'index.js');
const helpersPath = join(runtimeDirectory, 'dist', 'launchers', 'helpers.js');
let indexSource = await readFile(indexPath, 'utf8');
let helpersSource = await readFile(helpersPath, 'utf8');

if (indexSource.includes('build_utils_1.NodejsLambda') && helpersSource.includes('function normalizeBody')) {
    console.log('vercel-php 0.9.0 Node launcher compatibility fix is already applied.');
    process.exit(0);
}

const indexReplacements = [
    ['new build_utils_1.Lambda({', 'new build_utils_1.NodejsLambda({'],
    [
        "handler: 'launcher.launcher',",
        "handler: 'launcher.js',\n        awsLambdaHandler: 'launcher.launcher',\n        shouldAddHelpers: false,\n        shouldAddSourcemapSupport: false,",
    ],
];

for (const [before, after] of indexReplacements) {
    if (indexSource.split(before).length !== 2) {
        throw new Error(`Unexpected vercel-php builder source: expected one "${before}" occurrence.`);
    }

    indexSource = indexSource.replace(before, after);
}

const userDirectoryPattern = "const getUserDir = () => (0, path_1.join)(process.env.LAMBDA_TASK_ROOT || '/', 'user');";
const phpDirectoryPattern = "const getPhpDir = () => (0, path_1.join)(process.env.LAMBDA_TASK_ROOT || '/', 'php');";

if (helpersSource.split(userDirectoryPattern).length !== 2 || helpersSource.split(phpDirectoryPattern).length !== 2) {
    throw new Error('Unexpected vercel-php helper source: task-root paths could not be patched safely.');
}

helpersSource = helpersSource
    .replace(userDirectoryPattern, "const getTaskRoot = () => process.env.LAMBDA_TASK_ROOT || '/var/task';\nconst getUserDir = () => (0, path_1.join)(getTaskRoot(), 'user');")
    .replace(phpDirectoryPattern, "const getPhpDir = () => (0, path_1.join)(getTaskRoot(), 'php');");

const normalizeEventStart = helpersSource.indexOf('function normalizeEvent(event) {');
const transformRequestStart = helpersSource.indexOf('async function transformFromAwsRequest', normalizeEventStart);

if (normalizeEventStart === -1 || transformRequestStart === -1) {
    throw new Error('Unexpected vercel-php helper source: request normalization could not be patched safely.');
}

const normalizedRequestHandler = `function isRecord(value) {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
function isStringArray(value) {
    return Array.isArray(value) && value.every(item => typeof item === 'string');
}
function isByteArray(value) {
    return Array.isArray(value) && value.every(item => typeof item === 'number' && Number.isInteger(item) && item >= 0 && item <= 255);
}
function normalizeBody(body, encoding) {
    if (encoding !== undefined && encoding !== null && encoding !== 'utf8' && encoding !== 'base64') {
        throw new Error(\`Unsupported encoding: \${encoding}\`);
    }
    if (body === undefined || body === null)
        return undefined;
    if (Buffer.isBuffer(body))
        return body;
    if (isByteArray(body))
        return Buffer.from(body);
    if (typeof body === 'string')
        return Buffer.from(body, encoding === 'base64' ? 'base64' : 'utf8');
    throw new TypeError('Request body must be a string, Buffer, or array of bytes');
}
function normalizeHeaders(value) {
    if (value === undefined || value === null)
        return {};
    if (!isRecord(value))
        throw new TypeError('Request headers must be an object');
    const headers = {};
    for (const [name, header] of Object.entries(value)) {
        if (header !== undefined && typeof header !== 'string' && !isStringArray(header)) {
            throw new TypeError(\`Invalid request header: \${name}\`);
        }
        Object.defineProperty(headers, name, { value: header, enumerable: true });
    }
    return headers;
}
function getHeader(headers, name) {
    const key = Object.keys(headers).find(key => key.toLowerCase() === name);
    const value = key === undefined ? undefined : headers[key];
    return Array.isArray(value) ? value[0] : value;
}
function normalizeEvent(event) {
    if (!isRecord(event))
        throw new TypeError('Request event must be an object');
    const isInvoke = event.Action === 'Invoke';
    let request = event;
    if (isInvoke) {
        if (typeof event.body !== 'string')
            throw new TypeError('Invoke body must be JSON text');
        const parsed = JSON.parse(event.body);
        if (!isRecord(parsed))
            throw new TypeError('Invoke body must contain a request object');
        request = parsed;
    }
    const method = isInvoke ? request.method : request.httpMethod;
    const path = request.path;
    if (typeof method !== 'string' || typeof path !== 'string') {
        throw new TypeError('Request method and path must be strings');
    }
    if (request.host !== undefined && request.host !== null && typeof request.host !== 'string') {
        throw new TypeError('Request host must be a string');
    }
    const headers = normalizeHeaders(request.headers);
    const host = request.host || getHeader(headers, 'x-forwarded-host') || getHeader(headers, 'host') || '';
    if (!isInvoke && request.isBase64Encoded !== undefined &&
        request.isBase64Encoded !== null && typeof request.isBase64Encoded !== 'boolean') {
        throw new TypeError('isBase64Encoded must be a boolean');
    }
    const encoding = isInvoke ? request.encoding : request.isBase64Encoded === true ? 'base64' : 'utf8';
    return { method, path, host, headers, body: normalizeBody(request.body, encoding) };
}
`;

helpersSource = `${helpersSource.slice(0, normalizeEventStart)}${normalizedRequestHandler}${helpersSource.slice(transformRequestStart)}`;

await writeFile(indexPath, indexSource);
await writeFile(helpersPath, helpersSource);

console.log('Applied the vercel-php 0.9.0 Node launcher compatibility fix.');
