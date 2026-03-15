import axios from 'axios';
import type { Player } from '../types/Player';
import type { Standing } from '../types/Standing';
import type { Tournament } from '../types/Tournament';

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json'
  }
});

export const getLeaderboard = async (): Promise<Player[]> => {
  const res = await api.get('/leaderboard');
  return res.data;
};

export const getTournaments = async (): Promise<Tournament[]> => {
  const res = await api.get('/tournaments');
  return res.data;
};

export const getStandings = async (id: number): Promise<Standing[]> => {
  const res = await api.get(`/tournaments/${id}`);
  return res.data.players;
};

export default api;
