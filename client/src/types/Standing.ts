export interface Standing {
  player_id: number;
  player: {
    name: string;
  };
  swiss_wins: number;
  swiss_losses: number;
  swiss_rank: number;
}
