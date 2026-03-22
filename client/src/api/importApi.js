import api from './api';

export const importTournament = async (input, options = {}) => {
  const urls = Array.isArray(input)
    ? input
    : String(input)
        .split(/[\r\n,]+/)
        .map((value) => value.trim())
        .filter(Boolean);

  const payload = {
    ...(urls.length <= 1 ? { url: urls[0] ?? '' } : { urls }),
    ...(options.deferPlayerStatsRebuild ? { defer_player_stats_rebuild: true } : {})
  };
  const response = await api.post('/admin/import-tournament', payload);

  return response.data;
};

export const finalizeTournamentImports = async () => {
  const response = await api.post('/admin/import-tournament/finalize');

  return response.data;
};

export const recalculateLeaderboard = async () => {
  const response = await api.post('/admin/recalculate-leaderboard');

  return response.data;
};

export const prepareLeaderboardRecalculation = async () => {
  const response = await api.post('/admin/recalculate-leaderboard/prepare');

  return response.data;
};

export const recalculateTournamentLeaderboard = async (id) => {
  const response = await api.post(`/admin/recalculate-leaderboard/tournaments/${id}`);

  return response.data;
};

export const finalizeLeaderboardRecalculation = async () => {
  const response = await api.post('/admin/recalculate-leaderboard/finalize');

  return response.data;
};

export const correctPlayerName = async ({ aliasName, canonicalName }) => {
  const response = await api.post('/admin/player-corrections', {
    alias_name: aliasName,
    canonical_name: canonicalName
  });

  return response.data;
};
