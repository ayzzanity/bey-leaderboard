import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Button, Card, Form, Input, Progress, Select, Space, Typography } from 'antd';

import { batchUpdateTournaments, getTournaments } from '../api/api';
import { finalizeLeaderboardRecalculation, prepareLeaderboardRecalculation, recalculateTournamentLeaderboard } from '../api/importApi';

import { TournamentList } from '../components';

export default function Tournaments() {
  const [tournaments, setTournaments] = useState([]);
  const [selectedRowKeys, setSelectedRowKeys] = useState([]);
  const [batchUpdating, setBatchUpdating] = useState(false);
  const [filters, setFilters] = useState({
    q: '',
    player: '',
  });
  const [recalculating, setRecalculating] = useState(false);
  const [recalculationProgress, setRecalculationProgress] = useState(null);
  const [message, setMessage] = useState(null);
  const [error, setError] = useState(null);
  const [batchForm] = Form.useForm();

  useEffect(() => {
    loadTournaments();
  }, []);

  const loadTournaments = async (nextFilters = filters) => {
    const data = await getTournaments(nextFilters);
    setTournaments(data);
  };

  const handleSearch = async () => {
    setMessage(null);
    setError(null);
    setSelectedRowKeys([]);
    await loadTournaments(filters);
  };

  const handleBatchUpdate = async () => {
    setBatchUpdating(true);
    setMessage(null);
    setError(null);

    try {
      const values = await batchForm.validateFields();
      const result = await batchUpdateTournaments({
        tournament_ids: selectedRowKeys,
        age_category: values.age_category,
        event_type: values.event_type,
      });

      setMessage(`${result.message} Updated ${result.updated_count} tournaments.`);
      setSelectedRowKeys([]);
      batchForm.resetFields();
      await loadTournaments(filters);
    } catch (err) {
      if (err.errorFields) {
        return;
      }

      setError(err.response?.data?.message ?? 'Failed to batch update tournaments');
    } finally {
      setBatchUpdating(false);
    }
  };

  const handleRecalculate = async () => {
    setRecalculating(true);
    setRecalculationProgress(null);
    setMessage(null);
    setError(null);

    try {
      const preparation = await prepareLeaderboardRecalculation();
      const tournamentIds = preparation.tournament_ids ?? [];
      const total = preparation.total ?? tournamentIds.length;

      setRecalculationProgress({
        total,
        completed: 0,
        currentTournamentId: null,
        currentTournamentName: null,
      });

      for (let index = 0; index < tournamentIds.length; index += 1) {
        const tournamentId = tournamentIds[index];
        const tournament = tournaments.find((entry) => entry.id === tournamentId);

        setRecalculationProgress({
          total,
          completed: index,
          currentTournamentId: tournamentId,
          currentTournamentName: tournament?.name ?? `Tournament #${tournamentId}`,
        });

        await recalculateTournamentLeaderboard(tournamentId);

        setRecalculationProgress({
          total,
          completed: index + 1,
          currentTournamentId: tournamentId,
          currentTournamentName: tournament?.name ?? `Tournament #${tournamentId}`,
        });
      }

      const result = await finalizeLeaderboardRecalculation();
      setMessage(total > 0 ? `${result.message} Recalculated ${total} tournaments.` : 'No tournaments found to recalculate.');
      await loadTournaments();
    } catch (err) {
      setError(err.response?.data?.message ?? 'Server error while recalculating leaderboard');
    } finally {
      setRecalculating(false);
    }
  };

  return (
    <Card title="Tournaments">
      <div className="container">
        <div className="flex justify-end items-center m-2 gap-2">
          <Button onClick={handleRecalculate} loading={recalculating} disabled={recalculating}>
            {recalculating ? 'Recalculating...' : 'Recalculate Leaderboard'}
          </Button>
          <Link to="/admin/import">
            <Button>Import Tournament</Button>
          </Link>
        </div>

        {message && <Typography.Paragraph type="success">{message}</Typography.Paragraph>}
        {error && <Typography.Paragraph type="danger">{error}</Typography.Paragraph>}

        <Card size="small" className="mb-4" title="Search Tournaments">
          <Space wrap>
            <Input
              placeholder="Search by tournament name"
              value={filters.q}
              onChange={(event) => setFilters((current) => ({ ...current, q: event.target.value }))}
              style={{ width: 240 }}
            />
            <Input
              placeholder="Search by player name"
              value={filters.player}
              onChange={(event) => setFilters((current) => ({ ...current, player: event.target.value }))}
              style={{ width: 240 }}
            />
            <Button onClick={handleSearch}>Search</Button>
            <Button
              onClick={async () => {
                const resetFilters = { q: '', player: '' };
                setFilters(resetFilters);
                setSelectedRowKeys([]);
                await loadTournaments(resetFilters);
              }}
            >
              Clear
            </Button>
          </Space>
        </Card>

        {recalculationProgress && (
          <div className="mb-4">
            <Progress
              percent={recalculationProgress.total > 0 ? Math.round((recalculationProgress.completed / recalculationProgress.total) * 100) : 100}
              status={recalculating ? 'active' : 'success'}
            />
            <Typography.Paragraph className="mb-0">
              {recalculationProgress.total > 0
                ? `Processed ${recalculationProgress.completed} of ${recalculationProgress.total} tournaments`
                : 'No tournaments queued for recalculation.'}
            </Typography.Paragraph>
            {recalculationProgress.currentTournamentName && (
              <Typography.Text type="secondary">
                Current: {recalculationProgress.currentTournamentName}
              </Typography.Text>
            )}
          </div>
        )}

        {selectedRowKeys.length > 0 && (
          <Card size="small" className="mb-4" title={`Batch Update Categories${selectedRowKeys.length ? ` (${selectedRowKeys.length} selected)` : ''}`}>
            <Form form={batchForm} layout="inline">
              <Form.Item
                label="Category"
                name="age_category"
                rules={[{ required: true, message: 'Select a category' }]}
              >
                <Select
                  placeholder="Category"
                  style={{ width: 160 }}
                  options={[
                    { value: 'junior', label: 'Junior Cat' },
                    { value: 'open', label: 'Open Cat' }
                  ]}
                />
              </Form.Item>
              <Form.Item
                label="Event Type"
                name="event_type"
                rules={[{ required: true, message: 'Select an event type' }]}
              >
                <Select
                  placeholder="Event Type"
                  style={{ width: 160 }}
                  options={[
                    { value: 'casual', label: 'Casual' },
                    { value: 'tournament', label: 'Tournament' }
                  ]}
                />
              </Form.Item>
              <Form.Item>
                <Button
                  type="primary"
                  onClick={handleBatchUpdate}
                  loading={batchUpdating}
                  disabled={selectedRowKeys.length === 0 || batchUpdating}
                >
                  Update Selected
                </Button>
              </Form.Item>
            </Form>
          </Card>
        )}

        {tournaments.length === 0 ? <p>No tournaments found.</p> : (
          <TournamentList
            tournaments={tournaments}
            selectedRowKeys={selectedRowKeys}
            onSelectionChange={setSelectedRowKeys}
          />
        )}
      </div>
    </Card>
  );
}
