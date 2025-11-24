#!/usr/bin/env ts-node

import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

import Ajv from 'ajv';
import addFormats from 'ajv-formats';
import dotenv from 'dotenv';
import mysql, { Connection } from 'mysql2/promise';

import type { ImportOptions, QuizImportPayload } from './src/types';

dotenv.config();

const SCHEMA_PATH = path.resolve(__dirname, 'schema', 'quiz-import.schema.json');
const schema = JSON.parse(fs.readFileSync(SCHEMA_PATH, 'utf8'));

const ajv = new Ajv({ allErrors: true, strict: false });
addFormats(ajv);
const validate = ajv.compile<QuizImportPayload>(schema);

const DEFAULT_DB_PORT = 3306;
const DEFAULT_SCORE_VALUE = 0;
const DEFAULT_EMOJI = '✨';
const FALLBACK_EMOJIS = ['✨', '🎯', '🔥', '🌈', '💡', '🌟', '🚀', '🎉'];
const TAG_EMOJI_MAP: Record<string, string> = {
  love: '❤️',
  career: '💼',
  fun: '🎉',
  travel: '✈️',
  food: '🍜',
  sport: '🏃',
  money: '💰',
  study: '📚',
  personality: '🧠'
};

async function main() {
  try {
    const options = parseArgs(process.argv.slice(2));
    const payload = readPayload(options.filePath);
    validatePayload(payload);
    ensureEmoji(payload);

    const connection = await mysql.createConnection(getDbConfig());
    try {
      await connection.beginTransaction();
      const existingTest = await findTestBySlug(connection, payload.test.slug);

      if (existingTest && !options.overwrite) {
        throw new Error(
          `测验 slug "${payload.test.slug}" 已存在。使用 --overwrite 允许覆盖。`
        );
      }

      if (options.dryRun) {
        logDryRun(existingTest ? 'update' : 'create', payload, options);
        await connection.rollback();
        await connection.end();
        return;
      }

      const testId = await upsertTest(connection, payload, existingTest?.id ?? null);
      await replaceResults(connection, testId, payload.results);
      await replaceQuestions(connection, testId, payload);

      await connection.commit();
      console.log(`✅ 成功导入测验 "${payload.test.slug}"（ID: ${testId}）`);
      console.log(
        `   - 结果数：${payload.results.length}, 题目数：${payload.questions.length}`
      );
    } catch (error) {
      await connection.rollback();
      throw error;
    } finally {
      await connection.end();
    }
  } catch (error) {
    console.error('❌ 导入失败：');
    if (error instanceof Error) {
      console.error(error.message);
    } else {
      console.error(error);
    }
    process.exit(1);
  }
}

function parseArgs(args: string[]): ImportOptions {
  if (args.length === 0) {
    throw new Error('用法：yarn quiz:import <payload.json> [--overwrite] [--dry-run]');
  }

  const filePath = args[0];
  const overwrite = args.includes('--overwrite');
  const dryRun = args.includes('--dry-run');

  return { filePath, overwrite, dryRun };
}

function readPayload(filePath: string): QuizImportPayload {
  const absolute = path.resolve(process.cwd(), filePath);
  if (!fs.existsSync(absolute)) {
    throw new Error(`找不到文件：${absolute}`);
  }

  const raw = fs.readFileSync(absolute, 'utf8');
  return JSON.parse(raw);
}

function validatePayload(payload: QuizImportPayload) {
  if (!validate(payload)) {
    const messages =
      validate.errors?.map((err) => `- ${err.instancePath || '(root)'} ${err.message}`) ??
      [];
    throw new Error(`JSON Schema 校验失败：\n${messages.join('\n')}`);
  }

  payload.questions.forEach((question, idx) => {
    const keys = new Set<string>();
    question.options.forEach((option) => {
      if (keys.has(option.key)) {
        throw new Error(`题目 ${idx + 1} 包含重复选项 key "${option.key}"`);
      }
      keys.add(option.key);
    });
  });
}

function getDbConfig() {
  return {
    host: process.env.DB_HOST ?? '127.0.0.1',
    port: Number(process.env.DB_PORT ?? DEFAULT_DB_PORT),
    user: process.env.DB_USERNAME ?? 'root',
    password: process.env.DB_PASSWORD ?? '',
    database: process.env.DB_DATABASE ?? 'fun_quiz',
    charset: process.env.DB_CHARSET ?? 'utf8mb4',
    decimalNumbers: true,
    supportBigNumbers: true
  };
}

async function findTestBySlug(connection: Connection, slug: string) {
  const [rows] = await connection.execute(
    'SELECT id FROM tests WHERE slug = ? LIMIT 1',
    [slug]
  );
  const [existing] = rows as Array<{ id: number }>;
  return existing ?? null;
}

async function upsertTest(
  connection: Connection,
  payload: QuizImportPayload,
  existingId: number | null
) {
  const test = payload.test;
  const tags = test.tags.join(',').trim();
  const scoringMode = test.scoring_mode ?? 'simple';
  const scoringConfig = test.scoring_config
    ? JSON.stringify(test.scoring_config)
    : null;
  const displayMode = test.display_mode ?? 'single_page';
  const emoji = test.emoji ?? DEFAULT_EMOJI;
  const showSecondary = test.show_secondary_archetype !== false;
  const showDimensions = test.show_dimension_table !== false;

  if (existingId) {
    await connection.execute(
      `UPDATE tests
       SET title = ?, subtitle = ?, description = ?, title_color = ?, tags = ?,
           status = ?, sort_order = ?, scoring_mode = ?, scoring_config = ?,
           display_mode = ?, play_count_beautified = ?, emoji = ?,
           show_secondary_archetype = ?, show_dimension_table = ?
       WHERE id = ?`,
      [
        test.title,
        test.subtitle ?? null,
        test.description,
        test.title_color ?? '#4f46e5',
        tags || null,
        test.status,
        test.sort_order ?? 0,
        scoringMode,
        scoringConfig,
        displayMode,
        test.play_count_beautified ?? null,
        emoji,
        showSecondary ? 1 : 0,
        showDimensions ? 1 : 0,
        existingId
      ]
    );
    return existingId;
  }

  const [result] = await connection.execute(
    `INSERT INTO tests
       (slug, title, subtitle, description, title_color, tags, status, sort_order,
        scoring_mode, scoring_config, display_mode, play_count_beautified, emoji,
        show_secondary_archetype, show_dimension_table)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    [
      test.slug,
      test.title,
      test.subtitle ?? null,
      test.description,
      test.title_color ?? '#4f46e5',
      tags || null,
      test.status,
      test.sort_order ?? 0,
      scoringMode,
      scoringConfig,
      displayMode,
      test.play_count_beautified ?? null,
      emoji,
      showSecondary ? 1 : 0,
      showDimensions ? 1 : 0
    ]
  );

  const insertResult = result as mysql.ResultSetHeader;
  return insertResult.insertId;
}

async function replaceResults(
  connection: Connection,
  testId: number,
  results: QuizImportPayload['results']
) {
  await connection.execute('DELETE FROM results WHERE test_id = ?', [testId]);

  for (const result of results) {
    await connection.execute(
      `INSERT INTO results
        (test_id, code, title, description, image_url, min_score, max_score)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [
        testId,
        result.code,
        result.title,
        result.description,
        result.image_url ?? null,
        result.min_score ?? 0,
        result.max_score ?? 0
      ]
    );
  }
}

async function replaceQuestions(
  connection: Connection,
  testId: number,
  payload: QuizImportPayload
) {
  await connection.execute('DELETE FROM questions WHERE test_id = ?', [testId]);

  for (const [index, question] of payload.questions.entries()) {
    const [insertResult] = await connection.execute(
      'INSERT INTO questions (test_id, question_text, sort_order) VALUES (?, ?, ?)',
      [testId, question.text, index + 1]
    );

    const { insertId } = insertResult as mysql.ResultSetHeader;
    for (const option of question.options) {
      await connection.execute(
        `INSERT INTO question_options
          (question_id, option_key, option_text, map_result_code, score_value)
         VALUES (?, ?, ?, ?, ?)`,
        [
          insertId,
          option.key,
          option.text,
          option.map_result_code ?? null,
          resolveScore(option, payload.test.scoring_config)
        ]
      );
    }
  }
}

function resolveScore(
  option: { score_override?: number; key: string },
  scoringConfig?: QuizImportPayload['test']['scoring_config']
) {
  if (typeof option.score_override === 'number') {
    return option.score_override;
  }

  const optionScores = (scoringConfig as { option_scores?: Record<string, number> })?.option_scores;
  if (optionScores && typeof optionScores[option.key] === 'number') {
    return optionScores[option.key];
  }

  return DEFAULT_SCORE_VALUE;
}

function ensureEmoji(payload: QuizImportPayload) {
  payload.test.emoji = pickEmoji(payload);
}

function pickEmoji(payload: QuizImportPayload) {
  const explicit = sanitizeEmoji(payload.test.emoji);
  if (explicit) {
    return explicit;
  }

  for (const tag of payload.test.tags) {
    const mapped = TAG_EMOJI_MAP[tag.trim().toLowerCase()];
    if (mapped) {
      return mapped;
    }
  }

  return FALLBACK_EMOJIS[hashSlug(payload.test.slug)];
}

function sanitizeEmoji(candidate?: string | null) {
  if (!candidate) {
    return null;
  }
  const trimmed = candidate.trim();
  if (!trimmed) {
    return null;
  }
  const chars = Array.from(trimmed);
  if (chars.length > 16) {
    return chars.slice(0, 16).join('');
  }
  return trimmed;
}

function hashSlug(slug: string) {
  let hash = 0;
  for (let i = 0; i < slug.length; i += 1) {
    hash = (hash * 31 + slug.charCodeAt(i)) >>> 0;
  }
  return hash % FALLBACK_EMOJIS.length;
}

function logDryRun(
  action: 'create' | 'update',
  payload: QuizImportPayload,
  options: ImportOptions
) {
  console.log('🧪 Dry run 模式：不会写入数据库。');
  console.log(`   - 操作：${action === 'create' ? '创建新测验' : '覆盖现有测验'}`);
  console.log(`   - slug: ${payload.test.slug}`);
  console.log(`   - 标题: ${payload.test.title}`);
  console.log(
    `   - 结果数: ${payload.results.length}, 题目数: ${payload.questions.length}`
  );
  console.log(`   - overwrite: ${options.overwrite ? '是' : '否'}`);
}

main();

