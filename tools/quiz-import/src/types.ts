export type QuizImportPayload = {
  test: {
    slug: string;
    title: string;
    subtitle?: string;
    description: string;
    title_color?: string;
    tags: string[];
    status: 'draft' | 'published' | 'archived';
    sort_order?: number;
    scoring_mode?: 'simple' | 'dimensions' | 'range' | 'custom';
    scoring_config?: Record<string, unknown> | null;
    display_mode?: 'single_page' | 'step_by_step';
    play_count_beautified?: number | null;
    emoji?: string;
    show_secondary_archetype?: boolean;
    show_dimension_table?: boolean;
  };
  questions: Array<{
    text: string;
    hint?: string;
    options: Array<{
      key: string;
      text: string;
      map_result_code?: string;
      score_override?: number;
    }>;
  }>;
  results: Array<{
    code: string;
    title: string;
    description: string;
    image_url?: string;
    min_score?: number;
    max_score?: number;
    social_quote?: string;
  }>;
};

export type ImportOptions = {
  overwrite: boolean;
  dryRun: boolean;
  filePath: string;
};

