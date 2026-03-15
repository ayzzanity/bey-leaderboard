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

export const getTournaments = async () => {
  const res = await api.get('/tournaments');
  return res.data;
};

export const getStandings = async (id) => {
  const res = await api.get(`/tournaments/${id}`);
  return res.data.players;
};

export default api;
