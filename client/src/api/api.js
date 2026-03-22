import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json'
  }
});

export const getLeaderboard = async () => {
  const res = await api.get('/leaderboard');
  return res.data;
};

export const getTournaments = async (params = {}) => {
  const res = await api.get('/tournaments', { params });
  return res.data;
};

export const getStandings = async (id) => {
  const res = await api.get(`/tournaments/${id}`);
  return res.data;
};

export const updateTournament = async (id, payload) => {
  const res = await api.patch(`/tournaments/${id}`, payload);
  return res.data;
};

export const deleteTournament = async (id) => {
  const res = await api.delete(`/tournaments/${id}`);
  return res.data;
};

export const batchUpdateTournaments = async (payload) => {
  const res = await api.patch('/tournaments/batch', payload);
  return res.data;
};

export const getPlayerProfile = async (id) => {
  const res = await api.get(`/players/${id}`);
  return res.data;
};

export const searchPlayers = async (query, options = {}) => {
  const res = await api.get('/players', {
    params: {
      q: query,
      ...(options.excludeAliasName ? { exclude_alias_name: options.excludeAliasName } : {})
    }
  });

  return res.data;
};

export default api;
