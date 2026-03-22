// ========== bootstrapTheme.ts ==========

import { useMemo } from 'react';
import { theme } from 'antd';

const useDarkTheme = () => {
  return useMemo(
    () => ({
      theme: {
        algorithm: theme.defaultAlgorithm
      }
    }),
    []
  );
};
export default useDarkTheme;
